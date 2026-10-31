<?php

use Illuminate\Support\Facades\Schedule;

// بررسی روزانه‌ی تولد مشتری‌ها: پیامک تبریک، تخفیف تولد، اعلان خصوصی به ادمین‌ها
// و یک اعلان خلاصه برای ادمین‌ها (--notify).
// زمان بر اساس APP_TIMEZONE است. برای اجرای این زمان‌بندی، باید `php artisan schedule:run` هر دقیقه اجرا شود (cron).
Schedule::command('check:birthdays --notify')
    ->dailyAt('08:00')
    ->withoutOverlapping();

// ارسال کمپین‌های پیامکی گروهی در دسته‌های کوچک (با سقف روزانه در سرویس)
Schedule::command('sms:send-campaigns')
    ->everyTenMinutes()
    ->withoutOverlapping();

// پشتیبان خودکار روزانه (آخرین نسخه‌ها طبق BACKUP_KEEP نگه داشته می‌شوند)
Schedule::command('backup:run')
    ->dailyAt('02:30')
    ->withoutOverlapping();
