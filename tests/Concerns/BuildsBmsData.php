<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait BuildsBmsData
{
    protected function makeUser(string $role, array $attributes = []): User
    {
        // کاربر پذیرش باید به یک شعبه‌ی فعال متصل باشد (شعبه‌ی اصلی، که migration می‌سازد)
        if ($role === 'reception' && ! array_key_exists('branch_id', $attributes)) {
            $attributes['branch_id'] = Branch::mainId();
        }

        return User::create(array_merge([
            'name' => ucfirst($role).' '.uniqid(),
            'email' => $role.'.'.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    protected function makeCategory(): ProductCategory
    {
        return ProductCategory::create(['name' => 'Category '.uniqid(), 'is_active' => true]);
    }

    protected function makeProduct(array $attributes = []): Product
    {
        $admin = $attributes['created_by'] ?? $this->makeUser('admin')->id;

        return Product::create(array_merge([
            'name' => 'Product '.uniqid(),
            'category_id' => $this->makeCategory()->id,
            'price' => 10000,
            'stock_quantity' => 10,
            'min_stock' => 1,
            'is_active' => true,
            'status' => 'approved',
            'created_by' => $admin,
        ], $attributes));
    }

    protected function makeCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'membership_number' => Customer::generateMembershipNumber(),
            'first_name' => 'Ali',
            'last_name' => 'Test',
            'phone' => '0912'.random_int(1000000, 9999999),
            'total_purchases' => 0,
        ], $attributes));
    }
}
