<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // お店の設定（1行だけ）。FE の Settings 型に対応する
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('trial_price');
            $table->decimal('stripe_rate', 5, 4); // 返金額の計算用。実際の手数料は Stripe 側
            $table->unsignedTinyInteger('sessions_per_month');
            $table->unsignedTinyInteger('weekend_monthly_limit');
            $table->string('shop_phone', 32);
            $table->unsignedTinyInteger('schedule_open_day');
            $table->unsignedTinyInteger('schedule_deadline_day');
            $table->unsignedSmallInteger('checkout_hold_minutes'); // Stripe Checkout の期限は最短 30 分
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
