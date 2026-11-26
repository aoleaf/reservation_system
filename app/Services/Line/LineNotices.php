<?php

namespace App\Services\Line;

use App\Enums\ReservationKind;
use App\Enums\SlotStatus;
use App\Http\Presenters\BookingPresenter;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\Slot;
use App\Support\JaDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * お店の LINE グループへの通知（11/26 の決定）。
 * ・毎朝（config('booking.line_digest_at')）に、その日の予約をまとめて1通
 * ・朝のまとめを送ったあとに、その日の予約が入ったら、その予約だけを1通
 * 電話番号は載せない。送り先はスタッフが設定画面で決めたグループだけ。
 */
class LineNotices
{
    public function __construct(private readonly LineMessenger $line) {}

    /** その日の予約のまとめの文 */
    public function digestText(string $date): string
    {
        $p = BookingPresenter::make();
        $slots = $p->slots($date, $date);
        $lines = ['【つちのね】'.JaDate::dayLabel($date).'の予約'];
        if ($slots->isEmpty()) {
            return implode("\n", [...$lines, '', '本日は枠がありません']);
        }
        if ($slots->every(fn (Slot $s) => $s->status === SlotStatus::Closed)) {
            return implode("\n", [...$lines, '', '本日はお休みです']);
        }
        foreach ($slots as $slot) {
            $v = $p->slotView($slot, ReservationKind::Monthly);
            $head = "■ {$v['start']}〜{$v['end']}";
            if ($slot->status === SlotStatus::Closed) {
                $lines[] = '';
                $lines[] = "{$head}（お休み）";

                continue;
            }
            $head .= "（月謝{$v['used']['monthly']}・体験{$v['used']['trial']}／{$v['capacity']}".($slot->status === SlotStatus::Paused ? '・受付停止' : '').'）';
            $lines[] = '';
            $lines[] = $head;
            $entries = $p->entries($slot);
            if ($entries === []) {
                $lines[] = '予約なし';
            }
            foreach ($entries as $e) {
                $lines[] = $e['kind'] === 'monthly'
                    ? "・月謝 {$e['name']}"
                    : "・体験 {$e['name']} {$e['partySize']}名（".($e['payment'] === 'paid' ? '支払済' : '当日払い')."・{$e['channelLabel']}）";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * 朝のまとめを送る。送り先が無ければ送らない。同じ日に2回は送らない（force で送り直す）。
     *
     * @return bool 送ったか
     */
    public function sendDigest(string $date, bool $force = false): bool
    {
        $s = Setting::current();
        if (! $s->line_group_id) {
            Log::warning('LINE の送り先のグループが決まっていないので、朝のまとめを送りません');

            return false;
        }
        if (! $force && $s->line_digest_sent_at?->format('Y-m-d') === $date) {
            return false;
        }
        $this->line->push($s->line_group_id, $this->digestText($date), "digest-{$date}".($force ? '-'.now()->timestamp : ''));
        if ($date === JaDate::today()) {
            $s->update(['line_digest_sent_at' => CarbonImmutable::now()]);
        }

        return true;
    }

    /** 追加の予約の文 */
    public function addedText(Reservation $r): string
    {
        $r->loadMissing(['slot', 'member']);
        $slot = $r->slot;
        $time = "{$slot->startHm()}〜{$slot->endHm()}";
        $who = $r->kind === ReservationKind::Monthly
            ? '月謝 '.($r->member?->name ?? '')
            : "体験 {$r->contact_name} {$r->party_size}名（".($r->channel->value === 'web' ? '支払済' : '当日払い')."・{$r->channel->label()}）";

        return "【つちのね】本日の予約が追加されました\n{$time} {$who}";
    }

    /**
     * 朝のまとめを送ったあとに入った、今日の予約なら1通送る。それ以外は何もしない。
     *
     * @return bool 送ったか
     */
    public function notifyIfAddedAfterDigest(Reservation $r): bool
    {
        $s = Setting::current();
        $r->loadMissing('slot');
        $sentAt = $s->line_digest_sent_at;
        $today = JaDate::today();
        if (! $s->line_group_id || $r->slot->dateString() !== $today || $sentAt === null || $sentAt->format('Y-m-d') !== $today) {
            return false;
        }
        if (! $r->created_at || $r->created_at->lessThan($sentAt)) {
            return false; // 朝のまとめに入っている
        }
        $this->line->push($s->line_group_id, $this->addedText($r), "added-{$r->id}");

        return true;
    }
}
