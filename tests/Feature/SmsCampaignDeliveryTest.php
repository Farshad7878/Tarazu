<?php

namespace Tests\Feature;

use App\Models\SmsCampaign;
use App\Models\SmsCampaignRecipient;
use App\Services\SmsCampaignService;
use App\Services\SmsOutcome;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsBmsData;
use Tests\TestCase;

class SmsCampaignDeliveryTest extends TestCase
{
    use BuildsBmsData, RefreshDatabase;

    private function consentedCustomer(string $phone): void
    {
        $this->makeCustomer(['phone' => $phone, 'sms_consent_at' => now()]);
    }

    private function campaign(): SmsCampaign
    {
        $admin = $this->makeUser('admin');

        return app(SmsCampaignService::class)->create([
            'name' => 'تست',
            'message' => 'سلام {name}',
            'segment_type' => 'all',
        ], $admin);
    }

    public function test_attempt_reports_simulated_for_the_log_driver_and_send_stays_true(): void
    {
        $sms = app(SmsService::class);

        $this->assertSame(SmsOutcome::SIMULATED, $sms->attempt('09121234567', 'hello'));
        $this->assertTrue($sms->send('09121234567', 'hello'), 'listeners keep the old boolean contract');
        $this->assertFalse($sms->isLive());
    }

    public function test_log_driver_never_marks_a_campaign_recipient_as_sent(): void
    {
        config(['sms.driver' => 'log']);
        $this->consentedCustomer('09121111111');
        $campaign = $this->campaign();

        $result = app(SmsCampaignService::class)->sendPending();

        $this->assertSame(1, $result['simulated']);
        $this->assertSame(0, $result['sent']);

        $recipient = SmsCampaignRecipient::where('sms_campaign_id', $campaign->id)->firstOrFail();
        $this->assertSame('simulated', $recipient->status);
        $this->assertNull($recipient->sent_at);

        $campaign->refresh();
        $this->assertSame(0, $campaign->sent_count);
        $this->assertSame(1, $campaign->simulated_count);
    }

    public function test_log_driver_does_not_consume_the_daily_quota(): void
    {
        config(['sms.driver' => 'log', 'sms.campaign_daily_limit' => 1]);
        $this->consentedCustomer('09122222221');
        $this->consentedCustomer('09122222222');
        $this->campaign();

        $service = app(SmsCampaignService::class);

        // با سقف ۱ اگر شبیه‌سازی سهمیه مصرف می‌کرد، اجرای دوم هیچ گیرنده‌ای نمی‌گرفت
        $first = $service->sendPending();
        $second = $service->sendPending();

        $this->assertSame(1, $first['simulated']);
        $this->assertSame(1, $second['simulated']);
        $this->assertSame(1, $first['quota_left']);
        $this->assertSame(1, $second['quota_left']);
        $this->assertSame(0, SmsCampaignRecipient::where('status', 'pending')->count());
    }

    public function test_accepted_provider_response_is_recorded_as_sent(): void
    {
        config(['sms.driver' => 'kavenegar', 'sms.kavenegar.api_key' => 'KEY']);
        Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 200]], 200)]);
        $this->consentedCustomer('09123333333');
        $campaign = $this->campaign();

        $result = app(SmsCampaignService::class)->sendPending();

        $this->assertSame(1, $result['sent']);
        $this->assertSame(0, $result['simulated']);

        $recipient = SmsCampaignRecipient::where('sms_campaign_id', $campaign->id)->firstOrFail();
        $this->assertSame('sent', $recipient->status);
        $this->assertNotNull($recipient->sent_at);
        $this->assertSame(1, $campaign->fresh()->sent_count);
        $this->assertSame(0, $campaign->fresh()->simulated_count);
    }

    public function test_provider_rejection_is_recorded_as_failed(): void
    {
        config(['sms.driver' => 'kavenegar', 'sms.kavenegar.api_key' => 'KEY']);
        Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 411]], 400)]);
        $this->consentedCustomer('09124444444');
        $campaign = $this->campaign();

        $result = app(SmsCampaignService::class)->sendPending();

        $this->assertSame(1, $result['failed']);
        $this->assertSame('failed', SmsCampaignRecipient::where('sms_campaign_id', $campaign->id)->firstOrFail()->status);
        $this->assertSame(0, $campaign->fresh()->sent_count);
    }
}
