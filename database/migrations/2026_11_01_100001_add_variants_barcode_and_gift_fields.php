<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // نسخه‌ی محصول (سایز/رنگ): هر نسخه یک محصول فروشی مستقل است و به والد وصل می‌شود
            $table->foreignId('parent_product_id')->nullable()->after('category_id')
                ->constrained('products')->nullOnDelete();
            $table->string('variant_label', 100)->nullable()->after('parent_product_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            // شناسه‌ی یکتای سفارش از سمت کلاینت؛ برای جلوگیری از ثبت دوباره‌ی سفارش آفلاین هنگام همگام‌سازی
            $table->string('client_uuid', 36)->nullable()->unique()->after('order_number');
            $table->foreignId('gift_card_id')->nullable()->after('payment_method')
                ->constrained('gift_cards')->nullOnDelete();
            $table->decimal('gift_card_amount', 12, 2)->default(0)->after('gift_card_id');
        });

        Schema::table('order_returns', function (Blueprint $table) {
            // بخشی از مبلغ بازگشتی که به گیفت کارت برگشت داده شده (بقیه نقدی/کارتی است)
            $table->decimal('gift_card_credit', 12, 2)->default(0)->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropColumn('gift_card_credit');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_card_id');
            $table->dropColumn('gift_card_amount');
            $table->dropUnique(['client_uuid']);
            $table->dropColumn('client_uuid');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_product_id');
            $table->dropColumn('variant_label');
        });
    }
};
