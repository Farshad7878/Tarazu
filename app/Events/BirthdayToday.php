<?php

namespace App\Events;

use App\Models\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BirthdayToday implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $customer;
    public $birthdayMessage;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
        $this->birthdayMessage = $this->generateBirthdayMessage($customer);
    }

    public function broadcastOn()
    {
        // کانال خصوصی ادمین: اطلاعات مشتری (نام، تلفن، شماره عضویت) نباید عمومی باشد
        return new PrivateChannel('admin.dashboard');
    }

    public function broadcastWith()
    {
        return [
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->full_name,
            'membership_number' => $this->customer->membership_number,
            'phone' => $this->customer->phone,
            'birthday_message' => $this->birthdayMessage,
            'age' => $this->calculateAge($this->customer->date_of_birth),
            'notification_type' => 'birthday',
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    public function broadcastAs()
    {
        return 'birthday.today';
    }

    /**
     * تولید پیام تبریک تولد شخصی‌سازی شده
     */
    private function generateBirthdayMessage(Customer $customer): string
    {
        $age = $this->calculateAge($customer->date_of_birth);
        $firstName = $customer->first_name;

        $messages = [
            "🎂 تولد {$firstName} عزیز مبارک! امروز {$age} سالگی شما را تبریک می‌گوییم. 🎉",
            "🎁 {$firstName} گرامی، تولد {$age} سالگیتان مبارک! برای شما آرزوی بهترین‌ها را داریم. 🌟",
            "✨ تولدت مبارک {$firstName} عزیز! {$age} سالگی پر از شادی و موفقیت برای شما آرزومندیم. 🎈",
            "🎊 {$firstName} جان، تولد {$age} سالگیتان مبارک! امیدواریم سالی پر از برکت و شادی داشته باشید. 💫",
        ];

        return $messages[array_rand($messages)];
    }

    /**
     * محاسبه سن مشتری
     */
    private function calculateAge($birthDate): int
    {
        return $birthDate ? now()->diffInYears($birthDate) : 0;
    }
}