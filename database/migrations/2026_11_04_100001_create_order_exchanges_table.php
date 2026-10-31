<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_exchanges', function (Blueprint $table) {
            $table->id();
            // سفارش اصلی که کالا از آن برگشت، و سفارش جدیدی که کالای تازه از آن خریده شد
            $table->foreignId('original_order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('new_order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('order_return_id')->unique()->constrained('order_returns')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            // مابه‌التفاوت: مثبت = مشتری پرداخت می‌کند، منفی = به مشتری بازپرداخت می‌شود
            $table->decimal('difference', 15, 2);
            $table->timestamps();

            $table->index('original_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_exchanges');
    }
};
