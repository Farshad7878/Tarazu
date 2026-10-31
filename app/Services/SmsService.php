<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * نقطه‌ی واحد ارسال پیامک. همه‌ی لیسنرها (خوش‌آمد، تولد، تشکر) از همین سرویس استفاده می‌کنند.
 */
class SmsService
{
    /**
     * @return bool آیا پیامک با موفقیت پذیرفته شد؟ خطا هرگز به کاربر منتقل نمی‌شود.
     */
    public function send(?string $phone, string $message): bool
    {
        if (blank($phone)) {
            return false;
        }

        try {
            return match (config('sms.driver')) {
                'kavenegar' => $this->sendViaKavenegar($phone, $message),
                default => $this->logOnly($phone, $message),
            };
        } catch (Throwable $e) {
            Log::error('ارسال پیامک ناموفق بود: '.$e->getMessage(), ['phone' => $phone]);

            return false;
        }
    }

    private function logOnly(string $phone, string $message): bool
    {
        Log::info("[SMS:log] به {$phone}: {$message}");

        return true;
    }

    private function sendViaKavenegar(string $phone, string $message): bool
    {
        $apiKey = config('sms.kavenegar.api_key');

        if (blank($apiKey)) {
            Log::warning('SMS_DRIVER=kavenegar ولی SMS_KAVENEGAR_API_KEY تنظیم نشده است.');

            return false;
        }

        $response = Http::asForm()
            ->timeout(config('sms.kavenegar.timeout'))
            ->post(rtrim(config('sms.kavenegar.base_url'), '/')."/{$apiKey}/sms/send.json", array_filter([
                'receptor' => $phone,
                'sender' => config('sms.sender'),
                'message' => $message,
            ]));

        if ($response->successful() && $response->json('return.status') === 200) {
            return true;
        }

        Log::error('Kavenegar پیامک را نپذیرفت.', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        return false;
    }
}
