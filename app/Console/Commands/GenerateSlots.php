<?php

namespace App\Console\Commands;

use App\Enums\SlotStatus;
use App\Models\Setting;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * 1か月分の枠を作る（毎日3枠。土日は体験あり・月謝の上限あり）。
 * もうある枠は変えない（お休みや受付停止にした枠を上書きしない）。
 */
class GenerateSlots extends Command
{
    protected $signature = 'slots:generate {month? : YYYY-MM（省略すると翌々月）}';

    protected $description = '1か月分の枠を作る（もうある枠はそのまま）';

    public function handle(): int
    {
        $month = $this->argument('month') ?? CarbonImmutable::now()->addMonthsNoOverflow(2)->format('Y-m');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $this->error('month は YYYY-MM で指定してください');

            return self::FAILURE;
        }

        $settings = Setting::current();
        $first = CarbonImmutable::parse($month.'-01');
        $created = 0;
        for ($d = $first; $d->format('Y-m') === $month; $d = $d->addDay()) {
            foreach (config('booking.slot_times') as [$start, $end]) {
                $slot = Slot::query()->firstOrCreate(
                    ['date' => $d->format('Y-m-d'), 'start_time' => $start],
                    [
                        'end_time' => $end,
                        'capacity' => config('booking.capacity'),
                        'trial_open' => $d->isWeekend(),
                        'monthly_limit' => $d->isWeekend() ? $settings->weekend_monthly_limit : null,
                        'status' => SlotStatus::Open,
                    ],
                );
                $created += $slot->wasRecentlyCreated ? 1 : 0;
            }
        }

        $this->info("{$month}：{$created} 枠を作りました");

        return self::SUCCESS;
    }
}
