<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * کدهای کامل گیفت کارت که قبل از ماسک‌شدن در audit_logs.changes ذخیره شده‌اند، پاک‌سازی می‌شوند.
 * فقط مقدارهای خام (بدون *** ) تغییر می‌کنند و اجرای دوباره بی‌خطر است.
 * الگوی ماسک همان GiftCard::maskCode() است: '***' و ۴ کاراکتر آخر.
 *
 * برگشت‌ناپذیر است: کد کامل دیگر در لاگ نیست (نسخه‌های پشتیبان جدول gift_cards همچنان کد دارند).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('audit_logs')
            ->where('subject_type', 'GiftCard')
            ->whereNotNull('changes')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $changes = json_decode((string) $row->changes, true);

                    if (! is_array($changes) || ! isset($changes['code']) || ! is_array($changes['code'])) {
                        continue;
                    }

                    $touched = false;

                    foreach (['old', 'new'] as $side) {
                        $value = $changes['code'][$side] ?? null;

                        if (is_string($value) && $value !== '' && ! str_starts_with($value, '***')) {
                            $changes['code'][$side] = '***'.substr($value, -4);
                            $touched = true;
                        }
                    }

                    if ($touched) {
                        DB::table('audit_logs')
                            ->where('id', $row->id)
                            ->update(['changes' => json_encode($changes, JSON_UNESCAPED_UNICODE)]);
                    }
                }
            });
    }

    public function down(): void
    {
        // برگشت‌ناپذیر: کدهای کامل پس از ماسک‌شدن قابل بازیابی نیستند.
    }
};
