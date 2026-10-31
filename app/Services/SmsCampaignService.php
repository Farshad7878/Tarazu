<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\SmsCampaign;
use App\Models\SmsCampaignRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * پیامک گروهی (کمپین) با بخش‌بندی مشتری.
 *
 * قواعد:
 *  - فقط مشتری‌هایی که رضایت پیامک دارند و لغو نکرده‌اند گیرنده می‌شوند.
 *  - گیرنده‌ها هنگام ساخت کمپین ثبت می‌شوند؛ قبل از هر ارسال، وضعیت مشتری دوباره بررسی می‌شود.
 *  - هر گیرنده قبل از ارسال atomic claim می‌شود (pending → sending). فقط فرایندی که claim را برده پیامک می‌فرستد،
 *    پس حتی با چند فرایند همزمان، یک گیرنده دو بار پیامک نمی‌گیرد.
 *  - یک اجرای ارسال در هر لحظه (قفل cache)، تا سهمیه‌ی روزانه بین اجراهای همزمان دقیق بماند.
 *  - claim‌های گیر کرده (فرایند وسط ارسال قطع شده) دوباره ارسال نمی‌شوند؛ به وضعیت failed می‌روند.
 *  - ارسال با سقف روزانه (config sms.campaign_daily_limit) انجام می‌شود؛ باقی‌مانده در اجرای بعدی ارسال می‌شود.
 *  - هر پیامک لینک لغو اشتراک اختصاصی مشتری را دارد.
 */
class SmsCampaignService
{
    /** کلید قفل اجرای ارسال (بین همه‌ی سرورها، روی همان store کش) */
    public const DISPATCH_LOCK = 'sms:campaign-dispatch';

    /** حداکثر مدت قفل؛ بیشتر از زمان یک اجرای معمولی. اگر قفل منقضی شود، claim همچنان تکرار را جلوگیری می‌کند. */
    public const DISPATCH_LOCK_SECONDS = 900;

    /** claim‌ای که بیشتر از این مدت در وضعیت sending بماند، فرایندش قطع شده فرض می‌شود */
    public const STALE_CLAIM_MINUTES = 15;

    public function __construct(private SmsService $sms) {}

    public function audience(string $segment, ?int $days, ?float $minTotal): Builder
    {
        $query = Customer::smsReachable();

        if ($segment === 'inactive') {
            $since = now()->subDays((int) $days);
            $query->whereDoesntHave('orders', fn (Builder $orders) => $orders->where('created_at', '>=', $since));
        } elseif ($segment === 'big_spenders') {
            $query->where('total_purchases', '>=', (float) $minTotal);
        }

        return $query;
    }

    public function count(string $segment, ?int $days, ?float $minTotal): int
    {
        return $this->audience($segment, $days, $minTotal)->count();
    }

    public function create(array $data, User $user): SmsCampaign
    {
        return DB::transaction(function () use ($data, $user) {
            $campaign = SmsCampaign::create([
                'name' => $data['name'],
                'message' => $data['message'],
                'segment_type' => $data['segment_type'],
                'segment_days' => $data['segment_days'] ?? null,
                'segment_min_total' => $data['segment_min_total'] ?? null,
                'status' => 'active',
                'created_by' => $user->id,
            ]);

            $total = 0;

            $this->audience($data['segment_type'], $data['segment_days'] ?? null, $data['segment_min_total'] ?? null)
                ->select(['customers.id', 'customers.phone'])
                ->orderBy('customers.id')
                ->chunkById(500, function ($customers) use ($campaign, &$total) {
                    $now = now();
                    $rows = $customers->map(fn (Customer $customer) => [
                        'sms_campaign_id' => $campaign->id,
                        'customer_id' => $customer->id,
                        'phone' => $customer->phone,
                        'status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    SmsCampaignRecipient::insert($rows);
                    $total += count($rows);
                });

            $campaign->update(['total_recipients' => $total]);

            if ($total === 0) {
                $campaign->update(['status' => 'completed', 'completed_at' => now()]);
            }

            return $campaign->fresh();
        });
    }

    /**
     * ارسال گیرنده‌های در انتظار تا سقف روزانه. خروجی: تعداد هر وضعیت در این اجرا.
     *
     * فقط پیامکی که سرویس‌دهنده‌ی واقعی پذیرفته «sent» و از سهمیه کم می‌شود.
     * در حالت log (شبیه‌سازی) گیرنده «simulated» می‌شود و نه سهمیه را مصرف می‌کند نه در آمار ارسال‌شده می‌آید.
     * busy = true یعنی اجرای دیگری هم‌اکنون در حال ارسال است و این اجرا کاری نکرد.
     *
     * @return array{sent: int, simulated: int, failed: int, skipped: int, quota_left: int, busy: bool}
     */
    public function sendPending(): array
    {
        $lock = Cache::lock(self::DISPATCH_LOCK, self::DISPATCH_LOCK_SECONDS);

        if (! $lock->get()) {
            return ['sent' => 0, 'simulated' => 0, 'failed' => 0, 'skipped' => 0, 'quota_left' => 0, 'busy' => true];
        }

        try {
            return $this->dispatch();
        } finally {
            $lock->release();
        }
    }

    private function dispatch(): array
    {
        $this->recoverStaleClaims();

        $quotaLeft = max(0, (int) config('sms.campaign_daily_limit') - $this->reservedToday());
        $result = ['sent' => 0, 'simulated' => 0, 'failed' => 0, 'skipped' => 0, 'quota_left' => $quotaLeft, 'busy' => false];

        if ($quotaLeft > 0) {
            $batch = SmsCampaignRecipient::with(['campaign', 'customer'])
                ->where('status', 'pending')
                ->whereHas('campaign', fn (Builder $q) => $q->where('status', 'active'))
                ->orderBy('id')
                ->limit($quotaLeft)
                ->get();

            foreach ($batch as $recipient) {
                // اگر فرایند دیگری این گیرنده را برداشته باشد، کاری نمی‌کنیم
                if (! $this->claim($recipient)) {
                    continue;
                }

                $customer = $recipient->customer;

                // وضعیت مشتری ممکن است بعد از ساخت کمپین عوض شده باشد
                if (! $customer || ! $customer->sms_consent_at || $customer->sms_opt_out_at || blank($recipient->phone)) {
                    $this->finish($recipient, 'skipped', 'مشتری دیگر واجد شرایط پیامک نیست');
                    $result['skipped']++;

                    continue;
                }

                $outcome = $this->sms->attempt($recipient->phone, $this->render($recipient->campaign->message, $customer));

                if ($outcome === SmsOutcome::ACCEPTED) {
                    $this->finish($recipient, 'sent');
                    $result['sent']++;
                } elseif ($outcome === SmsOutcome::SIMULATED) {
                    $this->finish($recipient, 'simulated', 'درایور پیامک log است؛ پیامکی ارسال نشد');
                    $result['simulated']++;
                } else {
                    $this->finish($recipient, 'failed', 'ارسال پیامک ناموفق بود');
                    $result['failed']++;
                }
            }
        }

        SmsCampaign::where('status', 'active')
            ->whereDoesntHave('recipients', fn (Builder $q) => $q->whereIn('status', ['pending', 'sending']))
            ->update(['status' => 'completed', 'completed_at' => now()]);

        return $result;
    }

    /**
     * claim اتمیک: فقط اگر گیرنده هنوز pending است به sending می‌رود. خروجی true یعنی این فرایند مالک گیرنده شد.
     */
    private function claim(SmsCampaignRecipient $recipient): bool
    {
        $now = now();

        return SmsCampaignRecipient::whereKey($recipient->id)
            ->where('status', 'pending')
            ->update(['status' => 'sending', 'claimed_at' => $now, 'updated_at' => $now]) === 1;
    }

    /**
     * claim‌های قدیمی (فرایند وسط ارسال قطع شده) به failed می‌روند، نه pending.
     * دلیل: ممکن است پیامک قبلاً رفته باشد و تلاش دوباره باعث ارسال تکراری شود.
     */
    private function recoverStaleClaims(): void
    {
        $stale = SmsCampaignRecipient::where('status', 'sending')
            ->where('claimed_at', '<', now()->subMinutes(self::STALE_CLAIM_MINUTES))
            ->pluck('id');

        foreach ($stale as $id) {
            $updated = SmsCampaignRecipient::whereKey($id)
                ->where('status', 'sending')
                ->update([
                    'status' => 'failed',
                    'error' => 'نتیجه‌ی ارسال نامشخص است: فرایند قبل از ثبت نتیجه قطع شد (تلاش مجدد انجام نمی‌شود)',
                    'updated_at' => now(),
                ]);

            if ($updated === 1) {
                SmsCampaign::whereKey(SmsCampaignRecipient::whereKey($id)->value('sms_campaign_id'))
                    ->increment('failed_count');
            }
        }
    }

    /**
     * تعداد پیامک‌های امروز که سهمیه را مصرف کرده‌اند: ارسال‌شده‌ها و claim‌های در حال ارسال.
     */
    private function reservedToday(): int
    {
        $start = now()->startOfDay();

        return SmsCampaignRecipient::where(function (Builder $q) use ($start) {
            $q->where(fn (Builder $s) => $s->where('status', 'sent')->where('sent_at', '>=', $start))
                ->orWhere(fn (Builder $s) => $s->where('status', 'sending')->where('claimed_at', '>=', $start));
        })->count();
    }

    /**
     * لغو کمپین: گیرنده‌های در انتظار حذف نمی‌شوند ولی ارسال نمی‌شوند (وضعیت skipped).
     * گیرنده‌هایی که هم‌اکنون sending هستند تمام می‌شوند چون پیامکشان در جریان است.
     */
    public function cancel(SmsCampaign $campaign): SmsCampaign
    {
        return DB::transaction(function () use ($campaign) {
            $locked = SmsCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            $skipped = SmsCampaignRecipient::where('sms_campaign_id', $locked->id)
                ->where('status', 'pending')
                ->update(['status' => 'skipped', 'error' => 'کمپین لغو شد', 'updated_at' => now()]);

            $locked->update([
                'status' => 'cancelled',
                'skipped_count' => $locked->skipped_count + $skipped,
                'completed_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function render(string $message, Customer $customer): string
    {
        $text = str_replace('{name}', (string) $customer->first_name, $message);

        return $text."\nلغو پیامک: ".$this->unsubscribeUrl($customer);
    }

    public function unsubscribeUrl(Customer $customer): string
    {
        return url('/sms/unsubscribe/'.$customer->unsubscribe_token);
    }

    private function finish(SmsCampaignRecipient $recipient, string $status, ?string $error = null): void
    {
        $recipient->update([
            'status' => $status,
            'error' => $error,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);

        $column = [
            'sent' => 'sent_count',
            'simulated' => 'simulated_count',
            'failed' => 'failed_count',
            'skipped' => 'skipped_count',
        ][$status];

        SmsCampaign::whereKey($recipient->sms_campaign_id)->increment($column);
    }
}
