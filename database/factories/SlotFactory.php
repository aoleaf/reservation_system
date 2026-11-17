<?php

namespace Database\Factories;

use App\Enums\SlotStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Slot>
 */
class SlotFactory extends Factory
{
    /** 平日の 10:00 枠（月謝のみ、上限なし） */
    public function definition(): array
    {
        return [
            'date' => '2026-12-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'capacity' => 6,
            'trial_open' => false,
            'monthly_limit' => null,
            'status' => SlotStatus::Open,
        ];
    }

    /** 土日の枠（体験あり・月謝は3名まで） */
    public function weekend(string $date = '2026-12-05'): static
    {
        return $this->state(fn () => ['date' => $date, 'trial_open' => true, 'monthly_limit' => 3]);
    }

    public function status(SlotStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
