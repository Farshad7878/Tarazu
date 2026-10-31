<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Response;

/**
 * لینک لغو اشتراک پیامک گروهی. بدون ورود کاربر؛ امنیت با توکن تصادفی ۴۸ کاراکتری است.
 */
class SmsUnsubscribeController extends Controller
{
    public function __invoke(string $token): Response
    {
        $customer = Customer::where('unsubscribe_token', $token)->first();

        if (! $customer) {
            return $this->page('لینک نامعتبر است.', 404);
        }

        if ($customer->sms_opt_out_at === null) {
            $customer->forceFill(['sms_opt_out_at' => now()])->save();
        }

        return $this->page('دریافت پیامک‌های گروهی برای شما لغو شد. ✔️', 200);
    }

    private function page(string $message, int $status): Response
    {
        $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        return response(
            '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>لغو پیامک</title></head>'
            .'<body style="font-family:sans-serif;text-align:center;padding:60px 16px;">'
            ."<h2>{$safe}</h2></body></html>",
            $status,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}
