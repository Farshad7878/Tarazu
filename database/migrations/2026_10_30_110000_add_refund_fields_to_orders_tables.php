<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // مبلغ خالص: total_amount بعد از هر بازگشت کم می‌شود، و این ستون جمع بازگشتی‌ها را نگه می‌دارد
            $table->decimal('refunded_amount', 15, 2)->default(0)->after('total_amount');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('returned_quantity')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('returned_quantity');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('refunded_amount');
        });
    }
};
