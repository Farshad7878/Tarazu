<?php

namespace App\Services;

/**
 * نتیجه‌ی یک تلاش ارسال پیامک. این سه حالت با هم اشتباه گرفته نمی‌شوند:
 *  - ACCEPTED: سرویس‌دهنده‌ی واقعی (مثل کاوه‌نگار) پیامک را پذیرفته است.
 *  - SIMULATED: درایور log است؛ هیچ پیامکی به مشتری نرسیده و نرسیدن هم رخ نداده است.
 *  - FAILED: ارسال انجام نشد (خطای شبکه، کلید ناقص، شماره‌ی نامعتبر و …).
 */
enum SmsOutcome: string
{
    case ACCEPTED = 'accepted';
    case SIMULATED = 'simulated';
    case FAILED = 'failed';
}
