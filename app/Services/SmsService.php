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
     * آیا پیامک واقعاً به سرویس‌دهنده ارسال می‌شود؟ (درایور log یعنی خیر)
     */
    public function isLive(): bool
    {
        return config('sms.driver') !== 'log';
    }

    /**
     * برای لیسنرها (خوش‌آمد، تولد، تشکر). درایور log هم true برمی‌گرداند چون عمداً فقط لاگ می‌کند.
     * کسی که دقیق بداند پیامک واقعاً رفته یا نه، باید attempt() را صدا بزند.
     *
     * @return bool خطا هرگز به کاربر منتقل نمی‌شود.
     */
    public function send(?string $phone, string $message): bool
    {
        return $this->attempt($phone, $message) !== SmsOutcome::FAILED;
    }

    /**
     * تلاش ارسال با نتیجه‌ی صریح. کمپین‌ها باید از این استفاده کنند تا شبیه‌سازی را «ارسال‌شده» حساب نکنند.
     */
    public function attempt(?string $phone, string $message): SmsOutcome
    {
        if (blank($phone)) {
            return SmsOutcome::FAILED;
        }

        try {
            return match (config('sms.driver')) {
                'kavenegar' => $this->sendViaKavenegar($phone, $message)
                    ? SmsOutcome::ACCEPTED
                    : SmsOutcome::FAILED,
                default => $this->logOnly($phone, $message),
            };
        } catch (Throwable $e) {
            Log::error('ارسال پیامک ناموفق بود: '.$e->getMessage(), ['phone' => $phone]);

            return SmsOutcome::FAILED;
        }
    }

    private function logOnly(string $phone, string $message): SmsOutcome
    {
        Log::info("[SMS:log] به {$phone}: {$message}");

        return SmsOutcome::SIMULATED;
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
