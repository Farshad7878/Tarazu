<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // کاربر پذیرش به یک شعبه تعلق دارد؛ ادمین شعبه ندارد و به همه‌ی شعبه‌ها دسترسی دارد
            $table->foreignId('branch_id')->nullable()->after('role')->constrained('branches')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('reception_id')->constrained('branches')->nullOnDelete();
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('product_id')->constrained('branches')->nullOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('supplier_id')->constrained('branches')->nullOnDelete();
        });

        $mainId = DB::table('branches')->where('is_main', true)->value('id');
        if ($mainId) {
            DB::table('users')->where('role', 'reception')->whereNull('branch_id')->update(['branch_id' => $mainId]);
            DB::table('orders')->whereNull('branch_id')->update(['branch_id' => $mainId]);
            DB::table('stock_adjustments')->whereNull('branch_id')->update(['branch_id' => $mainId]);
            DB::table('purchase_orders')->whereNull('branch_id')->update(['branch_id' => $mainId]);
        }
    }

    public function down(): void
    {
        foreach (['purchase_orders', 'stock_adjustments', 'orders', 'users'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
