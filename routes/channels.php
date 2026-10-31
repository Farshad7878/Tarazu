<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// کانال داشبورد ادمین: فقط ادمین‌ها
Broadcast::channel('admin.dashboard', function (User $user) {
    return $user->role === 'admin';
});

// کانال شخصی هر کاربر (مثلاً اطلاع تخفیف برای ریسپشن)
Broadcast::channel('user.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});
