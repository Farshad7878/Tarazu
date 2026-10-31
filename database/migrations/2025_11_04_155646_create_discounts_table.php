<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['percentage', 'fixed_amount', 'free_product']);
            $table->decimal('value', 10, 2)->nullable();
            $table->enum('target', ['all', 'specific_customer']);
            $table->enum('condition_type', ['total_purchases', 'monthly_purchases', 'birthday', 'manual']);
            $table->decimal('condition_value', 15, 2)->nullable();
            $table->foreignId('free_product_id')->nullable()->constrained('products');
            $table->text('message');
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_apply')->default(false);
            $table->boolean('auto_disable_after_use')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('discounts');
    }
};