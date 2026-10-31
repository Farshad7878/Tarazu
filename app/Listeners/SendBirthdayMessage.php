<?php

namespace App\Listeners;

use App\Events\BirthdayToday;
use App\Models\Discount;
use App\Services\AdminNotifier;
use App\Services\DiscountService;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * پردازش تولد یک مشتری: پیامک تبریک، تخفیف ویژه‌ی تولد و اعلان برای ادمین‌ها.
 */
class SendBirthdayMessage implements ShouldQueue
{
    public function __construct(
        private DiscountService $discountService,
        private SmsService $sms,
        private AdminNotifier $notifier,
    ) {}

    public function handle(BirthdayToday $event): void
    {
        $customer = $event->customer;

        $this->sms->send($customer->phone, $event->birthdayMessage);

        $this->createBirthdayDiscount($customer);

        $this->notifier->notify('🎂 تولد مشتری', $event->birthdayMessage, [
            'customer_id' => $customer->id,
            'customer_name' => $customer->full_name,
            'membership_number' => $customer->membership_number,
            'type' => 'birthday',
            'action_url' => "/admin/customers/{$customer->id}",
        ]);
    }

    private function createBirthdayDiscount($customer): void
    {
        try {
            $birthdayDiscount = Discount::where('condition_type', 'birthday')
                ->where('is_active', true)
                ->first();

            if ($birthdayDiscount) {
                $this->discountService->assignBirthdayDiscount($customer, $birthdayDiscount);
            }
        } catch (\Throwable $e) {
            Log::error('خطا در ایجاد تخفیف تولد: '.$e->getMessage());
        }
    }
}
