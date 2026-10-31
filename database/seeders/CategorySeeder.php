<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * دسته‌بندی‌های پایه‌ی سیستم.
 * مدیر می‌تواند از پنل «مدیریت دسته‌بندی‌ها» هر تعداد دسته‌بندی دلخواه تعریف کند.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        ProductCategory::firstOrCreate(
            ['name' => 'عمومی'],
            [
                'icon' => '📦',
                'sku_prefix' => 'GEN',
                'description' => 'دسته‌بندی پیش‌فرض برای محصولات بدون دسته‌بندی مشخص',
                'sort_order' => 0,
                'is_active' => true,
            ]
        );
    }
}
