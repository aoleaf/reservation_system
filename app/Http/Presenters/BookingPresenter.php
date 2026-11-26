<?php

namespace App\Http\Presenters;

use App\Domain\Booking\Refund;
use App\Domain\Booking\Seats;
use App\Enums\CancelReason;
use App\Enums\ReservationKind;
use App\Enums\SlotStatus;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\Slot;
use App\Services\Booking\CancellationService;
use App\Support\JaDate;
use Carbon\CarbonImmutable;

/**
 * 画面の props のうち、枠と予約の形を作る（resources/js/mocks/props.ts の slotView・entriesOf などを移したもの）。
 * 形は resources/js/types/pages.ts の SlotView・AdminSlotCell・AdminEntry と同じにする。
 * 枠の予約は呼ぶ側で with('reservations') して渡す（席を使っている予約だけを読み込む）。
 */
final class BookingPresenter
{
    public function __construct(private readonly Setting $settings, private readonly CarbonImmutable $now) {}

    public static function make(): self
    {
        return new self(Setting::current(), CarbonImmutable::now());
    }

    public function today(): string
    {
        return $this->now->format('Y-m-d');
    }

    /** SlotView。extraOk が false なら押せない（前日まで、などの画面ごとの条件） */
    public function slotView(Slot $slot, ReservationKind $kind, bool $extraOk = true): array
    {
        $rs = $slot->reservations;
        $rest = Seats::remaining($slot, $rs, $kind, $this->now);
        $date = $slot->dateString();

        return [
            'id' => (string) $slot->id,
            'date' => $date,
            'weekday' => JaDate::weekday($date),
            'start' => $slot->startHm(),
            'end' => $slot->endHm(),
            'capacity' => $slot->capacity,
            'used' => [
                'monthly' => Seats::used($slot, $rs, ReservationKind::Monthly, $this->now),
                'trial' => Seats::used($slot, $rs, ReservationKind::Trial, $this->now),
            ],
            'remaining' => $rest,
            'bookable' => $slot->status === SlotStatus::Open && $rest > 0 && $extraOk,
            'status' => $slot->status->value,
        ];
    }

    /** AdminSlotCell：帯の内訳・印・お休みにできるか */
    public function adminSlotCell(Slot $slot): array
    {
        $v = $this->slotView($slot, ReservationKind::Monthly);
        $usedTotal = $v['used']['monthly'] + $v['used']['trial'];
        $count = $slot->reservations->filter(fn (Reservation $r) => $r->holdsSeat($this->now))->count();
        $monthlyFull = $slot->monthly_limit !== null && $v['used']['monthly'] >= $slot->monthly_limit;
        $free = max(0, $slot->capacity - $usedTotal);

        return $v + [
            'usedTotal' => $usedTotal,
            'fill' => self::fill($usedTotal, $slot->capacity),
            'trialOnlyFree' => $slot->status === SlotStatus::Open && $slot->trial_open && $monthlyFull ? $free : 0,
            'mark' => $slot->status === SlotStatus::Closed ? null : ($free === 0 ? 'full' : ($monthlyFull ? 'monthlyFull' : null)),
            'reservationCount' => $count,
            'canClose' => $count === 0,
        ];
    }

    /** 埋まり具合の濃さ：0 席=0、満席=4、その間は 1〜3 */
    public static function fill(int $used, int $capacity): int
    {
        if ($used <= 0) {
            return 0;
        }
        if ($used >= $capacity) {
            return 4;
        }

        return min(3, (int) ceil($used / $capacity * 3));
    }

    /** AdminEntry の一覧（月謝が先、同じ種類は申し込み順）。予約に member を読み込んでおく */
    public function entries(Slot $slot): array
    {
        return $slot->reservations
            ->filter(fn (Reservation $r) => $r->holdsSeat($this->now))
            ->sortBy([
                fn (Reservation $a, Reservation $b) => ($a->kind === ReservationKind::Monthly ? 0 : 1) <=> ($b->kind === ReservationKind::Monthly ? 0 : 1),
                fn (Reservation $a, Reservation $b) => $a->created_at <=> $b->created_at,
                fn (Reservation $a, Reservation $b) => $a->id <=> $b->id,
            ])
            ->map(fn (Reservation $r) => [
                'id' => (string) $r->id,
                'name' => ($r->member_id ? $r->member?->name : $r->contact_name) ?? '（名前なし）',
                'phone' => ($r->member_id ? $r->member?->phone : $r->contact_phone) ?: null,
                'kind' => $r->kind->value,
                'partySize' => $r->party_size,
                'payment' => $r->kind === ReservationKind::Monthly ? 'monthly' : (Refund::isPrepaid($r) ? 'paid' : 'onsite'),
                'channel' => $r->channel->value,
                'channelLabel' => $r->channel->label(),
                'cancel' => $this->staffCancelOf($r, $slot),
            ])
            ->values()
            ->all();
    }

    /**
     * スタッフがキャンセルしたときの返金額と説明を、理由ごとに。
     * customer：お客様のキャンセルと同じ規定 ／ shop：お店の都合で全額返金
     *
     * @return array{customer: array{refund: int, note: string}, shop: array{refund: int, note: string}}
     */
    public function staffCancelOf(Reservation $r, Slot $slot): array
    {
        if ($r->kind === ReservationKind::Monthly) {
            $note = '月謝の回をキャンセルします（返金なし・生徒さんの変更回数は使いません）。別の日に入れるときは、予約の入力から入れてください';

            return ['customer' => ['refund' => 0, 'note' => $note], 'shop' => ['refund' => 0, 'note' => $note]];
        }
        if (! Refund::isPrepaid($r)) {
            $note = '当日払いのため、返金はありません';

            return ['customer' => ['refund' => 0, 'note' => $note], 'shop' => ['refund' => 0, 'note' => $note]];
        }
        $r->setRelation('slot', $slot);
        $date = $slot->dateString();
        $when = $date >= JaDate::addDays($this->today(), 2) ? '2日前まで（決済手数料を引いて返金）'
            : ($date === JaDate::addDays($this->today(), 1) ? '前日（50%を返金）' : '当日以降（返金なし）');
        $paid = 'お支払い '.number_format($r->amount).'円';

        return [
            'customer' => ['refund' => CancellationService::refundFor($r, CancelReason::Customer, $this->today(), $this->settings->stripe_rate), 'note' => "{$paid}・{$when}"],
            'shop' => ['refund' => $r->amount, 'note' => "{$paid}・お店の都合のため全額返金（決済手数料はお店の負担）"],
        ];
    }

    /**
     * 期間の枠を、席を使っている予約（と生徒）つきで読み込む。日付・開始時刻の順。
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Slot>
     */
    public function slots(string $from, string $to): \Illuminate\Database\Eloquent\Collection
    {
        return Slot::query()
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')->orderBy('start_time')
            ->with(['reservations' => fn ($q) => $q->holdingSeat($this->now)->with('member')])
            ->get();
    }

    /** CalendarDay の共通部分 */
    public function calendarDay(string $date): array
    {
        return ['date' => $date, 'day' => (int) substr($date, 8, 2), 'weekdayIndex' => JaDate::weekdayIndex($date), 'isToday' => $date === $this->today()];
    }

    public function slotLabel(Slot $slot): string
    {
        return JaDate::dayLabel($slot->dateString()).' '.$slot->startHm().'〜'.$slot->endHm();
    }

    /** キャンセル規定（mocks の cancelPolicyOf） */
    public function cancelPolicy(): array
    {
        $rate = round($this->settings->stripe_rate * 100, 1);

        return [
            "2日前まで：お支払い額から決済手数料（{$rate}%）を引いて返金",
            '前日：お支払い額の50%を返金',
            '当日・無断のキャンセル：返金なし',
        ];
    }

    /** キャンセルの案内（リンクは確認メールで送る） */
    public function cancelGuide(): string
    {
        return "キャンセルは、予約確認メールに記載のキャンセル用リンクから、またはお電話（{$this->settings->shop_phone}）で承ります。";
    }

    public function settings(): Setting
    {
        return $this->settings;
    }

    public function now(): CarbonImmutable
    {
        return $this->now;
    }
}
