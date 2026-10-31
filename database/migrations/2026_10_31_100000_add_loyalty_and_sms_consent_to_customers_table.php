<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->unsignedInteger('loyalty_lifetime_points')->default(0);
            $table->timestamp('sms_consent_at')->nullable();
            $table->timestamp('sms_opt_out_at')->nullable();
            $table->string('unsubscribe_token', 64)->nullable()->unique();
        });

        // مشتری‌های قدیمی هم توکن لغو اشتراک می‌گیرند
        DB::table('customers')->whereNull('unsubscribe_token')->orderBy('id')->pluck('id')
            ->each(fn ($id) => DB::table('customers')->where('id', $id)
                ->update(['unsubscribe_token' => Str::random(48)]));
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['unsubscribe_token']);
            $table->dropColumn([
                'loyalty_points',
                'loyalty_lifetime_points',
                'sms_consent_at',
                'sms_opt_out_at',
                'unsubscribe_token',
            ]);
        });
    }
};
