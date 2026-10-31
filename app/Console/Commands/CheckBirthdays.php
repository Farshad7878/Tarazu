<?php

namespace App\Console\Commands;

use App\Events\BirthdayToday;
use App\Models\Customer;
use App\Services\AdminNotifier;
use Illuminate\Console\Command;

class CheckBirthdays extends Command
{
    protected $signature = 'check:birthdays
                            {--notify : ثبت یک اعلان خلاصه‌ی روزانه برای ادمین‌ها}
                            {--test : افزودن مشتری نمونه (id=1) برای تست}';

    protected $description = 'بررسی تولد امروز مشتری‌ها: پیامک تبریک، تخفیف تولد و اعلان به ادمین‌ها';

    public function handle(AdminNotifier $notifier): int
    {
        $today = now();

        $customers = Customer::query()
            ->whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', $today->month)
            ->whereDay('date_of_birth', $today->day)
            ->get();

        if ($this->option('test')) {
            $customers = $customers->merge(Customer::where('id', 1)->get())->unique('id')->values();
        }

        $this->info("🔍 بررسی تولدهای امروز: {$today->format('m-d')}");

        if ($customers->isEmpty()) {
            $this->info('🎉 امروز هیچ مشتری تولد ندارد.');

            return self::SUCCESS;
        }

        $this->info("🎂 تعداد {$customers->count()} مشتری امروز تولد دارند.");

        foreach ($customers as $customer) {
            // پردازش هر تولد در لیسنر صف‌بندی‌شده انجام می‌شود
            event(new BirthdayToday($customer));
            $this->line("🎁 {$customer->full_name} - شماره عضویت: {$customer->membership_number}");
        }

        if ($this->option('notify')) {
            $names = $customers->pluck('full_name')->implode('، ');

            $notifier->notify(
                '🎂 تولدهای امروز',
                "امروز {$customers->count()} مشتری تولد دارند: {$names}",
                ['type' => 'birthday_summary', 'count' => $customers->count()]
            );

            $this->info('📢 اعلان خلاصه برای ادمین‌ها ثبت شد.');
        }

        $this->info('✅ بررسی تولدها با موفقیت انجام شد.');

        return self::SUCCESS;
    }
}
