<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('customer_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('discount_id')->constrained()->onDelete('cascade');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            
            $table->unique(['customer_id', 'discount_id', 'used_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_discounts');
    }
};