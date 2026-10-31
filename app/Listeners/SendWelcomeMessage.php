<?php

namespace App\Listeners;

use App\Events\CustomerRegistered;
use App\Models\Message;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * پیام خوشامدگویی به مشتری جدید (صف‌بندی‌شده، تا ارسال پیامک درخواست را معطل نکند).
 * نکته: جدول notifications به users وصل است و نباید شناسه‌ی مشتری در آن ذخیره شود.
 */
class SendWelcomeMessage implements ShouldQueue
{
    public function __construct(private SmsService $sms) {}

    public function handle(CustomerRegistered $event): void
    {
        $welcomeMessage = Message::where('type', 'welcome')
            ->where('is_active', true)
            ->first();

        if (! $welcomeMessage) {
            return;
        }

        $body = str_replace('{name}', $event->customer->full_name, $welcomeMessage->body);

        $this->sms->send($event->customer->phone, $body);
    }
}
