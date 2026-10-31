<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تعداد گیرنده‌هایی که در حالت شبیه‌سازی (درایور log) پردازش شده‌اند.
 * این عدد جدا از sent_count است تا آمار «ارسال‌شده» فقط پیامک‌های واقعاً پذیرفته‌شده را نشان دهد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_campaigns', function (Blueprint $table) {
            $table->unsignedInteger('simulated_count')->default(0)->after('skipped_count');
        });
    }

    public function down(): void
    {
        Schema::table('sms_campaigns', function (Blueprint $table) {
            $table->dropColumn('simulated_count');
        });
    }
};
