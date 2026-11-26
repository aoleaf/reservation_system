<?php

namespace App\Console\Commands;

use App\Services\Line\LineNotices;
use App\Support\JaDate;
use Illuminate\Console\Command;

/** お店の LINE グループに、その日の予約のまとめを送る（毎朝。同じ日に2回は送らない） */
class LineDailyDigest extends Command
{
    protected $signature = 'line:daily-digest {--date= : YYYY-MM-DD（省略すると今日）} {--force : 送ったあとでも送り直す}';

    protected $description = 'お店の LINE グループに、その日の予約のまとめを送る';

    public function handle(LineNotices $notices): int
    {
        $date = $this->option('date') ?? JaDate::today();
        if (! JaDate::isDate($date)) {
            $this->error('date は YYYY-MM-DD で指定してください');

            return self::FAILURE;
        }
        $sent = $notices->sendDigest($date, (bool) $this->option('force'));
        $this->info($sent ? "{$date} のまとめを送りました" : "{$date} のまとめは送りませんでした（送り先が未設定か、送り済み）");

        return self::SUCCESS;
    }
}
