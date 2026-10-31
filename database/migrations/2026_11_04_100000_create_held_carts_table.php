<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('held_carts', function (Blueprint $table) {
            $table->id();
            // سبد معلق متعلق به همان ریسپشن است
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            // [{product_id, quantity}]؛ موجودی هنگام معلق‌کردن رزرو نمی‌شود
            $table->json('items');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_carts');
    }
};
