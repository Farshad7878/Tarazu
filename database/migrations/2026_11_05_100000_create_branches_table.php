<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 30)->unique();
            $table->string('address', 255)->nullable();
            $table->string('phone', 30)->nullable();
            // شعبه‌ی اصلی: موجودی‌ها و سفارش‌های قبل از چندشعبه‌ای به آن منتقل می‌شوند و غیرفعال‌شدنی نیست
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('branches')->insert([
            'name' => 'شعبه مرکزی',
            'code' => 'MAIN',
            'address' => null,
            'phone' => null,
            'is_main' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
