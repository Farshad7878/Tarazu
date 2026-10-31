<?php

namespace App\Events;

use App\Models\Customer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewCustomerRegistered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $customer;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('admin.dashboard');
    }

    public function broadcastWith()
    {
        return [
            'customer_id' => $this->customer->id,
            'membership_number' => $this->customer->membership_number,
            'full_name' => $this->customer->full_name,
            'phone' => $this->customer->phone,
            'created_at' => $this->customer->created_at->toDateTimeString(),
        ];
    }
}