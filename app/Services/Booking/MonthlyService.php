<?php

namespace App\Services\Booking;

use App\Domain\Booking\ScheduleWindow;
use App\Domain\Booking\Seats;
use App\Enums\CanceledBy;
use App\Enums\CancelReason;
use App\Enums\Channel;
use App\Enums\ReservationKind;
use App\Enums\ReservationStatus;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\Slot;
use App\Support\JaDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 生徒さんの日程の提出と日程変更（mocks/actions.ts の submitMonthlySchedule・changeMonthlySession に当たる）。
 * 枠をロックしてから残席を数え直す。生徒さん自身の変更だけが「月1回」を使う（管理画面からの変更は数えない：森下様の回答 Q2）。
 */
class MonthlyService
{
    /** @param  list<int|string>  $slotIds */
    public function submit(Member $member, string $month, array $slotIds): void
    {
        DB::transaction(function () use ($member, $month, $slotIds) {
            $now = CarbonImmutable::now();
            $settings = Setting::current();
            $mm = $member->monthOf($month);
            if ($mm->submitted_at !== null) {
                $this->fail('slotIds', 'この月はもう提出済みです');
            }
            if (ScheduleWindow::forMonth($month, $settings, $now->format('Y-m-d'))['state'] !== 'open') {
                $this->fail('slotIds', 'この月の日程の受付期間ではありません。お店にご連絡ください');
            }
            $ids = array_values(array_unique(array_map('intval', $slotIds)));
            if (count($ids) !== $settings->sessions_per_month) {
                $this->fail('slotIds', "{$settings->sessions_per_month}つ選んでください");
            }
            // デッドロックしないよう、ID の順にロックする
            sort($ids);
            $slots = Slot::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($slots->count() !== count($ids) || $slots->contains(fn (Slot $s) => JaDate::monthOf($s->dateString()) !== $month)) {
                $this->fail('slotIds', '選べない枠が含まれています');
            }
            if ($slots->map(fn (Slot $s) => $s->dateString())->unique()->count() !== $slots->count()) {
                $this->fail('slotIds', '同じ日に2つの枠は選べません');
            }
            $tomorrow = JaDate::addDays($now->format('Y-m-d'), 1);
            foreach ($slots as $slot) {
                if ($slot->dateString() < $tomorrow || ! Seats::canBook($slot, $slot->holdingReservations($now)->get(), ReservationKind::Monthly, 1, $now)) {
                    $this->fail('slotIds', '満席になった枠があります。選び直してください');
                }
            }
            foreach ($slots as $slot) {
                $this->createMonthly($member, $slot, $now);
            }
            $mm->submitted_at = $now;
            $mm->save();
        });
    }

    public function change(Member $member, Reservation $reservation, int|string $toSlotId): void
    {
        DB::transaction(function () use ($member, $reservation, $toSlotId) {
            $now = CarbonImmutable::now();
            $r = Reservation::query()->lockForUpdate()->with('slot')->find($reservation->id);
            if (! $r || $r->member_id !== $member->id || $r->kind !== ReservationKind::Monthly || ! $r->holdsSeat($now)) {
                $this->fail('reservationId', '予約が見つかりません');
            }
            $month = JaDate::monthOf($r->slot->dateString());
            $mm = $member->monthOf($month);
            $tomorrow = JaDate::addDays($now->format('Y-m-d'), 1);
            if ($mm->changeRemaining() <= 0) {
                $this->fail('reservationId', 'この月の日程変更はもう使いました');
            }
            if ($r->slot->dateString() < $tomorrow) {
                $this->fail('reservationId', '日程変更は前日までです');
            }
            $to = Slot::query()->lockForUpdate()->find($toSlotId);
            if (! $to || JaDate::monthOf($to->dateString()) !== $month || $to->dateString() < $tomorrow) {
                $this->fail('toSlotId', '選べない枠です');
            }
            $otherDates = $member->sessionsIn($month)->reject(fn (Reservation $x) => $x->id === $r->id)->map(fn (Reservation $x) => $x->slot->dateString());
            if ($otherDates->contains($to->dateString())) {
                $this->fail('toSlotId', '同じ日にもう予約があります');
            }
            if (! Seats::canBook($to, $to->holdingReservations($now)->get(), ReservationKind::Monthly, 1, $now)) {
                $this->fail('toSlotId', StaffReservationService::FULL);
            }

            $r->update(['status' => ReservationStatus::Canceled, 'canceled_at' => $now, 'canceled_by' => CanceledBy::Customer, 'cancel_reason' => CancelReason::Customer]);
            $this->createMonthly($member, $to, $now);
            $mm->change_used = true;
            $mm->submitted_at ??= $now;
            $mm->save();
        });
    }

    private function createMonthly(Member $member, Slot $slot, CarbonImmutable $now): Reservation
    {
        return Reservation::query()->create([
            'code' => Reservation::newCode($now),
            'slot_id' => $slot->id,
            'kind' => ReservationKind::Monthly,
            'channel' => Channel::Member,
            'party_size' => 1,
            'status' => ReservationStatus::Confirmed,
            'amount' => 0,
            'member_id' => $member->id,
        ]);
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
