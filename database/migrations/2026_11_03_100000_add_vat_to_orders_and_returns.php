<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // نرخ ارزش افزوده‌ی زمان ثبت سفارش (درصد) و مالیات موجود در مبلغ خالص فعلی سفارش
            $table->decimal('tax_rate', 5, 2)->default(0)->after('total_amount');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate');
        });

        Schema::table('order_returns', function (Blueprint $table) {
            // مالیاتی که با این بازگشت از سفارش کم شد
            $table->decimal('tax_refund', 15, 2)->default(0)->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropColumn('tax_refund');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount']);
        });
    }
};
