<?php

namespace Tests\Feature;

use App\Models\SmsCampaign;
use App\Models\SmsCampaignRecipient;
use App\Services\SmsCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsBmsData;
use Tests\TestCase;

class SmsCampaignClaimTest extends TestCase
{
    use BuildsBmsData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sms.driver' => 'kavenegar', 'sms.kavenegar.api_key' => 'KEY']);
        Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 200]], 200)]);
    }

    private function campaignWithCustomers(int $count): SmsCampaign
    {
        for ($i = 0; $i < $count; $i++) {
            $this->makeCustomer(['phone' => '0912'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT), 'sms_consent_at' => now()]);
        }

        return app(SmsCampaignService::class)->create([
            'name' => 'تست claim',
            'message' => 'سلام {name}',
            'segment_type' => 'all',
        ], $this->makeUser('admin'));
    }

    public function test_a_recipient_held_by_another_process_is_not_sent_again(): void
    {
        $campaign = $this->campaignWithCustomers(1);
        $recipient = SmsCampaignRecipient::where('sms_campaign_id', $campaign->id)->firstOrFail();

        // فرایند دیگری همین گیرنده را claim کرده و هنوز در حال ارسال است
        $recipient->update(['status' => 'sending', 'claimed_at' => now()]);

        $result = app(SmsCampaignService::class)->sendPending();

        Http::assertNothingSent();
        $this->assertSame(0, $result['sent']);
        $this->assertSame('sending', $recipient->fresh()->status);
    }

    public function test_each_recipient_is_sent_exactly_once_across_repeated_runs(): void
    {
        $campaign = $this->campaignWithCustomers(3);
        $service = app(SmsCampaignService::class);

        $first = $service->sendPending();
        $second = $service->sendPending();

        $this->assertSame(3, $first['sent']);
        $this->assertSame(0, $second['sent']);
        Http::assertSentCount(3);

        $this->assertSame(0, SmsCampaignRecipient::where('sms_campaign_id', $campaign->id)
            ->whereIn('status', ['pending', 'sending'])->count());
        $this->assertSame(3, $campaign->fresh()->sent_count);
    }

    public function test_a_stale_claim_is_marked_failed_and_never_resent(): void
    {
        $campaign = $this->campaignWithCustomers(1);
        $recipient = SmsCampaignRecipient::where('sms_campaign_id', $campaign->id)->firstOrFail();

        // فرایندی که وسط ارسال قطع شده است
        $recipient->update(['status' => 'sending', 'claimed_at' => now()->subMinutes(SmsCampaignService::STALE_CLAIM_MINUTES + 5)]);

        $result = app(SmsCampaignService::class)->sendPending();

        Http::assertNothingSent();
        $this->assertSame(0, $result['sent']);

        $fresh = $recipient->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertStringContainsString('تلاش مجدد انجام نمی‌شود', (string) $fresh->error);
        $this->assertSame(1, $campaign->fresh()->failed_count);
    }

    public function test_in_flight_claims_count_toward_the_daily_quota(): void
    {
        config(['sms.campaign_daily_limit' => 1]);
        $campaign = $this->campaignWithCustomers(2);
        $held = SmsCampaignRecipient::where('sms_campaign_id', $campaign->id)->orderBy('id')->firstOrFail();

        // یک ارسال امروز در حال انجام است و سهمیه‌ی ۱ را گرفته
        $held->update(['status' => 'sending', 'claimed_at' => now()]);

        $result = app(SmsCampaignService::class)->sendPending();

        Http::assertNothingSent();
        $this->assertSame(0, $result['quota_left']);
    }

    public function test_a_second_dispatch_while_the_lock_is_held_does_nothing(): void
    {
        $campaign = $this->campaignWithCustomers(1);

        $lock = Cache::lock(SmsCampaignService::DISPATCH_LOCK, SmsCampaignService::DISPATCH_LOCK_SECONDS);
        $this->assertTrue($lock->get());

        try {
            $result = app(SmsCampaignService::class)->sendPending();
        } finally {
            $lock->release();
        }

        $this->assertTrue($result['busy']);
        Http::assertNothingSent();
        $this->assertSame(1, SmsCampaignRecipient::where('sms_campaign_id', $campaign->id)->where('status', 'pending')->count());
    }

    public function test_campaign_is_not_completed_while_a_recipient_is_still_sending(): void
    {
        $campaign = $this->campaignWithCustomers(1);
        SmsCampaignRecipient::where('sms_campaign_id', $campaign->id)->update(['status' => 'sending', 'claimed_at' => now()]);

        app(SmsCampaignService::class)->sendPending();

        $this->assertSame('active', $campaign->fresh()->status);
    }
}
