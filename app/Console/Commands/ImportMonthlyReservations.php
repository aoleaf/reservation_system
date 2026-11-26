<?php

namespace App\Console\Commands;

use App\Domain\Booking\Seats;
use App\Enums\Channel;
use App\Enums\ReservationKind;
use App\Enums\ReservationStatus;
use App\Enums\SlotStatus;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\Slot;
use App\Support\CsvFile;
use App\Support\JaDate;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 月謝の予約を CSV から取り込む。見出し：メールアドレス, 日付, 開始時刻（1行に1回）
 * 生徒さんはメールアドレスで探す（先に import:members）。同じ予約は飛ばす。
 * 満席（土日の月謝の上限も）・お休みの枠・同じ日に2回はエラー。1行でもエラーがあれば何も入れない。
 * その月が4回そろった生徒さんは、提出済みにする。
 */
class ImportMonthlyReservations extends Command
{
    protected $signature = 'import:monthly {file : CSV のパス} {--dry-run : 入れずに結果だけ見る}';

    protected $description = '月謝の予約を CSV から取り込む（メールアドレス, 日付, 開始時刻）';

    public function handle(): int
    {
        try {
            $rows = CsvFile::read($this->argument('file'), ['メールアドレス', '日付', '開始時刻']);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $errors = [];
        $created = $skipped = 0;
        $touched = []; // [member_id][month] = true
        $now = CarbonImmutable::now();

        DB::beginTransaction();
        foreach ($rows as ['line' => $line, 'row' => $r]) {
            $member = Member::query()->whereRaw('lower(email) = ?', [mb_strtolower($r['メールアドレス'])])->first();
            $date = $this->date($r['日付']);
            $start = $this->time($r['開始時刻']);
            if (! $member) {
                $errors[] = "{$line}行目：メールアドレス {$r['メールアドレス']} の生徒さんがいません（先に生徒さんの一覧を取り込んでください）";

                continue;
            }
            if (! $date || ! $start) {
                $errors[] = "{$line}行目：日付（2026-12-03 か 2026/12/3）と開始時刻（10:00）を確かめてください";

                continue;
            }
            $slot = Slot::query()->where('date', $date)->where('start_time', 'like', $start.'%')->first();
            if (! $slot) {
                $errors[] = "{$line}行目：{$date} {$start} の枠がありません";

                continue;
            }
            if ($member->reservations()->where('slot_id', $slot->id)->holdingSeat($now)->exists()) {
                $skipped++; // もう入っている

                continue;
            }
            if ($slot->status === SlotStatus::Closed) {
                $errors[] = "{$line}行目：{$date} {$start} はお休みの枠です";

                continue;
            }
            $month = JaDate::monthOf($date);
            if ($member->sessionsIn($month)->contains(fn (Reservation $x) => $x->slot->dateString() === $date)) {
                $errors[] = "{$line}行目：{$member->name} さんは {$date} にもう予約があります（同じ日に2回は入れられません）";

                continue;
            }
            // 取り込みでは受付停止の枠にも入れる（もともとの予約を移すため）。定員と土日の上限は守る
            $check = $slot->replicate()->forceFill(['id' => $slot->id, 'status' => SlotStatus::Open]);
            if (! Seats::canBook($check, $slot->holdingReservations($now)->get(), ReservationKind::Monthly, 1, $now)) {
                $errors[] = "{$line}行目：{$date} {$start} は満席です（土日の月謝は".Setting::current()->weekend_monthly_limit.'名まで）';

                continue;
            }
            Reservation::query()->create([
                'code' => Reservation::newCode($now), 'slot_id' => $slot->id, 'kind' => ReservationKind::Monthly, 'channel' => Channel::Staff,
                'party_size' => 1, 'status' => ReservationStatus::Confirmed, 'amount' => 0, 'member_id' => $member->id,
            ]);
            $created++;
            $touched[$member->id][$month] = true;
        }

        if ($errors) {
            DB::rollBack();
            array_map(fn ($e) => $this->error($e), $errors);
            $this->error('エラーがあるので、何も取り込んでいません');

            return self::FAILURE;
        }

        // 4回そろった月は提出済みにする
        $per = Setting::current()->sessions_per_month;
        $submitted = 0;
        foreach ($touched as $memberId => $months) {
            $member = Member::query()->find($memberId);
            foreach (array_keys($months) as $month) {
                $mm = $member->monthOf($month);
                if ($mm->submitted_at === null && $member->sessionsIn($month)->count() >= $per) {
                    $mm->submitted_at = $now;
                    $mm->save();
                    $submitted++;
                }
            }
        }

        $this->option('dry-run') ? DB::rollBack() : DB::commit();
        $this->info(($this->option('dry-run') ? '（確認だけ・入れていません）' : '')."入れた {$created} 件、もう入っていた {$skipped} 件、提出済みにした {$submitted} 件（生徒×月）");

        return self::SUCCESS;
    }

    /** '2026-12-03'・'2026/12/3' を 'YYYY-MM-DD' に */
    private function date(string $v): ?string
    {
        if (! preg_match('#^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$#', $v, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }

    /** '10:00'・'9:30' を 'HH:MM' に */
    private function time(string $v): ?string
    {
        return preg_match('/^(\d{1,2}):(\d{2})$/', $v, $m) ? sprintf('%02d:%02d', $m[1], $m[2]) : null;
    }
}
