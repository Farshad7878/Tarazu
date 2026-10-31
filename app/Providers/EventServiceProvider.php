<?php

namespace App\Providers;

use App\Events\BirthdayToday;
use App\Events\CustomerRegistered;
use App\Events\OrderCreated;
use App\Listeners\SendBirthdayMessage;
use App\Listeners\SendThankYouMessage;
use App\Listeners\BroadcastNewCustomer;
use App\Listeners\SendWelcomeMessage;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        CustomerRegistered::class => [
            SendWelcomeMessage::class,
            BroadcastNewCustomer::class,
        ],
        OrderCreated::class => [
            SendThankYouMessage::class,
        ],
        BirthdayToday::class => [
            SendBirthdayMessage::class,
        ],
    ];

    public function boot()
    {
        //
    }
}