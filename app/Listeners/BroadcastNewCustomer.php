<?php

namespace App\Listeners;

use App\Events\CustomerRegistered;
use App\Events\NewCustomerRegistered;

/**
 * اطلاع‌رسانی زنده به داشبورد ادمین وقتی مشتری جدیدی ثبت می‌شود.
 * تنها نقطه‌ی ارسال NewCustomerRegistered (ثبت مشتری فقط از مدل Customer رویداد می‌دهد).
 */
class BroadcastNewCustomer
{
    public function handle(CustomerRegistered $event): void
    {
        NewCustomerRegistered::dispatch($event->customer);
    }
}
