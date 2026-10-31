<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // احراز هویت کانال‌های خصوصی با توکن Sanctum (SPA با Bearer token کار می‌کند)
    // لیسنرها فقط از EventServiceProvider::$listen ثبت می‌شوند.
    // با discovery فعال، هر لیسنر یک‌بار از $listen و یک‌بار به‌صورت Class@handle ثبت می‌شد (اجرای دوباره).
    ->withEvents(discover: false)
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'prefix' => 'api',
        'middleware' => ['auth:sanctum'],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'reception' => \App\Http\Middleware\ReceptionMiddleware::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
