<?php

namespace App\Services;

/**
 * محاسبه‌ی ارزش افزوده برای مبلغ‌های «شامل مالیات».
 *
 * قیمت‌ها شامل مالیات‌اند، پس مالیات داخل مبلغ است و مبلغ پرداختی تغییر نمی‌کند:
 *   مالیات = مبلغ × نرخ ÷ (۱۰۰ + نرخ)
 * مثال: ۱۰۹٬۰۰۰ با نرخ ۹٪ ← مالیات ۹٬۰۰۰ و خالص ۱۰۰٬۰۰۰.
 */
class TaxService
{
    /** نرخ فعلی به درصد. مقدار نامعتبر (منفی یا ≥ ۱۰۰) به‌عنوان غیرفعال (۰) در نظر گرفته می‌شود. */
    public function rate(): float
    {
        $rate = (float) config('tax.vat_rate', 0);

        return ($rate > 0 && $rate < 100) ? round($rate, 2) : 0.0;
    }

    /** مالیات موجود در یک مبلغ شامل‌مالیات، گرد شده به دو رقم اعشار */
    public function includedIn(float|int|string $amount, float $rate): float
    {
        $amount = max(0.0, (float) $amount);

        if ($rate <= 0) {
            return 0.0;
        }

        return round($amount * $rate / (100 + $rate), 2);
    }
}
