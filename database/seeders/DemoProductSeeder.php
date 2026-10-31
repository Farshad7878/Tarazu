<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * داده‌های نمونه برای آزمایش سیستم (اختیاری).
 * اجرا: php artisan db:seed --class=DemoProductSeeder
 */
class DemoProductSeeder extends Seeder
{
    public function run()
    {
        $admin = User::where('role', 'admin')->first();

        if (! $admin) {
            $this->command?->warn('ابتدا یک کاربر ادمین ایجاد کنید (AdminUserSeeder یا از پنل). نمونه‌داده‌ها ساخته نشد.');
            return;
        }

        $demoCategories = [
            'juice' => ['name' => 'آبمیوه', 'icon' => '🍹', 'sku_prefix' => 'JUICE', 'sort_order' => 1],
            'ice_cream' => ['name' => 'بستنی', 'icon' => '🍦', 'sku_prefix' => 'ICE', 'sort_order' => 2],
        ];

        $categoryIds = [];
        foreach ($demoCategories as $key => $definition) {
            $categoryIds[$key] = ProductCategory::firstOrCreate(
                ['name' => $definition['name']],
                $definition
            )->id;
        }

        $products = [
            ['name' => 'آب پرتقال طبیعی', 'category' => 'juice', 'price' => 25000],
            ['name' => 'آب هویج', 'category' => 'juice', 'price' => 22000],
            ['name' => 'اسموتی میوه‌ای', 'category' => 'juice', 'price' => 30000],
            ['name' => 'بستنی وانیلی', 'category' => 'ice_cream', 'price' => 15000],
            ['name' => 'بستنی شکلاتی', 'category' => 'ice_cream', 'price' => 16000],
            ['name' => 'بستنی توت فرنگی', 'category' => 'ice_cream', 'price' => 17000],
        ];

        foreach ($products as $product) {
            Product::create([
                'name' => $product['name'],
                'category_id' => $categoryIds[$product['category']],
                'price' => $product['price'],
                'stock_quantity' => 100,
                'created_by' => $admin->id,
                'status' => 'approved',
            ]);
        }
    }
}
