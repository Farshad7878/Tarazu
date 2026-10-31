<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        // firstOrCreate: اجرای مجدد seeder خطای ایمیل تکراری نمی‌دهد.
        // رمز پیش‌فرض را بعد از اولین ورود تغییر دهید.
        User::firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true
        ]);

        // ایجاد یک کاربر ریسپشن نمونه
        User::firstOrCreate(['email' => 'reception@example.com'], [
            'name' => 'Reception User',
            'password' => Hash::make('password'),
            'role' => 'reception',
            'branch_id' => \App\Models\Branch::mainId(),
            'is_active' => true
        ]);
    }
}