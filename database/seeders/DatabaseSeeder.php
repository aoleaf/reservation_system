<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 開発用のデータ（モックと同じ生徒・予約）。本番では入れない
        if (! app()->isProduction()) {
            $this->call(BookingDemoSeeder::class);
        }
    }
}
