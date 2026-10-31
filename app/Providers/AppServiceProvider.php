<?php

namespace App\Providers;

use App\Services\DiscountService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DiscountService::class, function ($app) {
            return new DiscountService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}
}
