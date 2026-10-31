<?php

return [

    /*
    | درایور پیامک: "log" (پیش‌فرض، فقط در لاگ ثبت می‌شود) یا "kavenegar".
    | تا وقتی کلید واقعی تنظیم نشده، هیچ پیامکی به مشتری ارسال نمی‌شود.
    */
    'driver' => env('SMS_DRIVER', 'log'),

    'sender' => env('SMS_SENDER'),

    'kavenegar' => [
        'api_key' => env('SMS_KAVENEGAR_API_KEY'),
        'base_url' => env('SMS_KAVENEGAR_BASE_URL', 'https://api.kavenegar.com/v1'),
        'timeout' => 10,
    ],

    /*
    | ارسال پیامک تشکر بعد از هر سفارش. به‌دلیل هزینه‌ی پیامک، پیش‌فرض خاموش است.
    */
    'order_thank_you_enabled' => env('SMS_ORDER_THANK_YOU', false),

    /*
    | سقف پیامک کمپین‌های گروهی در هر روز (تقویمی، بر اساس APP_TIMEZONE).
    | جلوی ارسال انبوه و هزینه‌ی ناخواسته را می‌گیرد.
    */
    'campaign_daily_limit' => (int) env('SMS_CAMPAIGN_DAILY_LIMIT', 200),

];
