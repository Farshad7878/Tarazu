<?php

use App\Http\Controllers\Public\SmsUnsubscribeController;
use Illuminate\Support\Facades\Route;

// لغو اشتراک پیامک گروهی (لینک داخل پیامک). باید قبل از fallback SPA باشد.
Route::get('/sms/unsubscribe/{token}', SmsUnsubscribeController::class)
    ->where('token', '[A-Za-z0-9]{20,64}');

// SPA fallback: همه‌ی مسیرها به نمای اصلی Vue می‌روند، به‌جز مسیرهای API.
// بدون این استثنا، /api/<مسیر ناموجود> به‌جای 404 با HTML اپ پاسخ 200 می‌داد.
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api(?:/|$)).*');
