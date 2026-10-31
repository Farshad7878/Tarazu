<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
        });

        // موجودی فعلی هر محصول (پیش از چندشعبه‌ای) به شعبه‌ی اصلی منتقل می‌شود
        $mainId = DB::table('branches')->where('is_main', true)->value('id');
        if ($mainId) {
            $now = now();
            $rows = DB::table('products')->select('id', 'stock_quantity')->get()->map(fn ($p) => [
                'branch_id' => $mainId,
                'product_id' => $p->id,
                'quantity' => (int) $p->stock_quantity,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('branch_stocks')->insert($chunk);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_stocks');
    }
};
