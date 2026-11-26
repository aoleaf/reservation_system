<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 返金：Stripe につなぐまでは記録だけ（PR 4 で Stripe の実装に差し替える）
        $this->app->bind(\App\Services\Booking\RefundGateway::class, \App\Services\Booking\PendingRefundGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
