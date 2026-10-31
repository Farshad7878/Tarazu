<?php

namespace Tests\Feature;

use App\Services\SmsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    public function test_log_driver_is_the_default_and_reports_success(): void
    {
        $this->assertSame('log', config('sms.driver'));
        $this->assertTrue(app(SmsService::class)->send('09121234567', 'hello'));
    }

    public function test_blank_phone_is_not_sent(): void
    {
        $this->assertFalse(app(SmsService::class)->send(null, 'hello'));
    }

    public function test_kavenegar_without_api_key_returns_false(): void
    {
        config(['sms.driver' => 'kavenegar', 'sms.kavenegar.api_key' => null]);

        $this->assertFalse(app(SmsService::class)->send('09121234567', 'hello'));
    }

    public function test_kavenegar_rejection_returns_false_without_throwing(): void
    {
        config(['sms.driver' => 'kavenegar', 'sms.kavenegar.api_key' => 'KEY']);
        Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 411]], 400)]);

        $this->assertFalse(app(SmsService::class)->send('09121234567', 'hello'));
    }

    public function test_kavenegar_network_error_returns_false_without_throwing(): void
    {
        config(['sms.driver' => 'kavenegar', 'sms.kavenegar.api_key' => 'KEY']);
        Http::fake(fn () => throw new ConnectionException('down'));

        $this->assertFalse(app(SmsService::class)->send('09121234567', 'hello'));
    }
}
