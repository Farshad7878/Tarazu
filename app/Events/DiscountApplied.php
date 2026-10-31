<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * اطلاع به ریسپشنی که سفارش را ثبت کرده: یک تخفیف روی سفارش اعمال شد.
 */
class DiscountApplied implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $receptionId,
        public string $orderNumber,
        public string $discountName,
        public string $message,
        public float $amount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->receptionId}")];
    }

    public function broadcastAs(): string
    {
        return 'DiscountApplied';
    }

    public function broadcastWith(): array
    {
        return [
            'order_number' => $this->orderNumber,
            'discount_name' => $this->discountName,
            'message' => $this->message,
            'amount' => $this->amount,
        ];
    }
}
