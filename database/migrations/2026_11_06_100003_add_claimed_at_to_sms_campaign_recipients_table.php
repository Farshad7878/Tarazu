<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * زمان claim شدن گیرنده توسط یک فرایند ارسال.
 * وضعیت جدید sending یعنی این گیرنده در حال ارسال است و توسط هیچ فرایند دیگری برداشته نمی‌شود.
 * این ستون برای شمارش سهمیه‌ی روزانه‌ی ارسال‌های در حال انجام هم استفاده می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_campaign_recipients', function (Blueprint $table) {
            $table->timestamp('claimed_at')->nullable()->after('sent_at');
            $table->index(['status', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sms_campaign_recipients', function (Blueprint $table) {
            $table->dropIndex(['status', 'claimed_at']);
            $table->dropColumn('claimed_at');
        });
    }
};
