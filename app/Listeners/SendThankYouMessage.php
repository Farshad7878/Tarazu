<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * پیامک تشکر بعد از ثبت سفارش. فقط وقتی SMS_ORDER_THANK_YOU=true باشد ارسال می‌شود.
 */
class SendThankYouMessage implements ShouldQueue
{
    public function __construct(private SmsService $sms) {}

    public function handle(OrderCreated $event): void
    {
        if (! config('sms.order_thank_you_enabled')) {
            return;
        }

        $order = $event->order->loadMissing('customer');

        $message = sprintf(
            'از خرید شما سپاسگزاریم. سفارش %s به مبلغ %s تومان ثبت شد.',
            $order->order_number,
            number_format((float) $order->total_amount)
        );

        $this->sms->send($order->customer?->phone, $message);
    }
}
