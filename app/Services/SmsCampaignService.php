<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\SmsCampaign;
use App\Models\SmsCampaignRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * پیامک گروهی (کمپین) با بخش‌بندی مشتری.
 *
 * قواعد:
 *  - فقط مشتری‌هایی که رضایت پیامک دارند و لغو نکرده‌اند گیرنده می‌شوند.
 *  - گیرنده‌ها هنگام ساخت کمپین ثبت می‌شوند؛ قبل از هر ارسال، وضعیت مشتری دوباره بررسی می‌شود.
 *  - ارسال با سقف روزانه (config sms.campaign_daily_limit) انجام می‌شود؛ باقی‌مانده در اجرای بعدی ارسال می‌شود.
 *  - هر پیامک لینک لغو اشتراک اختصاصی مشتری را دارد.
 */
class SmsCampaignService
{
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
     *
     * @return array{sent: int, simulated: int, failed: int, skipped: int, quota_left: int}
     */
    public function sendPending(): array
    {
        $sentToday = SmsCampaignRecipient::where('status', 'sent')
            ->where('sent_at', '>=', now()->startOfDay())
            ->count();

        $quotaLeft = max(0, (int) config('sms.campaign_daily_limit') - $sentToday);
        $result = ['sent' => 0, 'simulated' => 0, 'failed' => 0, 'skipped' => 0, 'quota_left' => $quotaLeft];

        if ($quotaLeft > 0) {
            $batch = SmsCampaignRecipient::with(['campaign', 'customer'])
                ->where('status', 'pending')
                ->whereHas('campaign', fn (Builder $q) => $q->where('status', 'active'))
                ->orderBy('id')
                ->limit($quotaLeft)
                ->get();

            foreach ($batch as $recipient) {
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
            ->whereDoesntHave('recipients', fn (Builder $q) => $q->where('status', 'pending'))
            ->update(['status' => 'completed', 'completed_at' => now()]);

        return $result;
    }

    /**
     * لغو کمپین: گیرنده‌های در انتظار حذف نمی‌شوند ولی ارسال نمی‌شوند (وضعیت skipped).
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
