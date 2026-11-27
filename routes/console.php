<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 毎月1日に、翌々月の枠を作る
Illuminate\Support\Facades\Schedule::command('slots:generate')->monthlyOn(1, '03:00');

// 期限が切れた仮押さえを戻す（Stripe の期限切れの通知が届かなかったときの保険）
Illuminate\Support\Facades\Schedule::command('reservations:expire-holds')->everyMinute();

// 毎朝、お店の LINE グループにその日の予約のまとめを送る（日本時間）
Illuminate\Support\Facades\Schedule::command('line:daily-digest')->dailyAt(config('booking.line_digest_at'));
