<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * محصولاتی که قبل از افزودن بارکد ثبت شده‌اند، بارکد EAN-13 می‌گیرند.
 * فقط ردیف‌های بدون بارکد لمس می‌شوند؛ اجرای دوباره هم بی‌خطر است.
 * الگوی کد همان Product::generateBarcode() است (پیشوند ۲۰ و کد کنترلی EAN-13).
 */
return new class extends Migration
{
    public function up(): void
    {
        $taken = [];

        DB::table('products')
            ->where(function ($q) {
                $q->whereNull('barcode')->orWhere('barcode', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$taken) {
                foreach ($rows as $row) {
                    DB::table('products')
                        ->where('id', $row->id)
                        ->update(['barcode' => $this->newCode($taken)]);
                }
            });
    }

    public function down(): void
    {
        // برگشت‌ناپذیر و بی‌خطر: نمی‌دانیم کدام بارکدها توسط این مهاجرت ساخته شده‌اند،
        // پس چیزی حذف نمی‌شود.
    }

    private function newCode(array &$taken): string
    {
        do {
            $body = '20'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
            $sum = 0;
            foreach (str_split($body) as $i => $digit) {
                $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
            }
            $code = $body.((10 - $sum % 10) % 10);
        } while (isset($taken[$code]) || DB::table('products')->where('barcode', $code)->exists());

        $taken[$code] = true;

        return $code;
    }
};
