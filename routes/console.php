<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 毎月1日に、翌々月の枠を作る
Illuminate\Support\Facades\Schedule::command('slots:generate')->monthlyOn(1, '03:00');
