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
        // 返金：決済の窓口（Stripe）で行う
        $this->app->bind(\App\Services\Booking\RefundGateway::class, \App\Services\Booking\PaymentRefundGateway::class);

        // 決済：Stripe のキーがあれば本物、無ければ開発用の偽物（本番でキーが無いのは設定の誤りなので止める）
        $this->app->singleton(\App\Services\Payments\PaymentGateway::class, function ($app) {
            $secret = config('services.stripe.secret');
            if ($secret) {
                return new \App\Services\Payments\StripePaymentGateway($secret, (string) config('services.stripe.webhook_secret'));
            }
            if ($app->isProduction()) {
                throw new \RuntimeException('STRIPE_SECRET が設定されていません');
            }

            return new \App\Services\Payments\FakePaymentGateway;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
