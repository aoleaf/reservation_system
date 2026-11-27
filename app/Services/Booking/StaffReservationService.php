<?php

namespace App\Services\Booking;

use App\Domain\Booking\Seats;
use App\Enums\Channel;
use App\Enums\ReservationKind;
use App\Enums\ReservationStatus;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\User;
use App\Support\JaDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * スタッフの予約入力（電話・DM・来店）。mocks/actions.ts の createStaffReservation に当たる。
 * 枠をロックしてから残席を数え直すので、同時に入れてもダブルブッキングしない。
 */
class StaffReservationService
{
    public const FULL = '満席になりました。別の枠をお選びください';

    /** @param  array{kind: string, slotId: int|string, channel: string, partySize?: int, contact?: array{name: string, phone?: ?string}, memberId?: int|string}  $input */
    public function create(array $input, User $staff): Reservation
    {
        return DB::transaction(function () use ($input, $staff) {
            $now = CarbonImmutable::now();
            $settings = Setting::current();
            $slot = Slot::query()->lockForUpdate()->find($input['slotId']);
            if (! $slot) {
                throw ValidationException::withMessages(['slotId' => '枠が見つかりません']);
            }
            $holding = $slot->holdingReservations($now)->get();
            $channel = Channel::from($input['channel']);

            if ($input['kind'] === ReservationKind::Monthly->value) {
                return $this->createMonthly($slot, $holding, $input, $channel, $staff, $settings, $now);
            }

            $n = (int) $input['partySize'];
            if (! Seats::canBook($slot, $holding, ReservationKind::Trial, $n, $now)) {
                throw ValidationException::withMessages(['slotId' => self::FULL]);
            }

            return Reservation::query()->create([
                'code' => Reservation::newCode($now),
                'slot_id' => $slot->id,
                'kind' => ReservationKind::Trial,
                'channel' => $channel,
                'party_size' => $n,
                'status' => ReservationStatus::Confirmed,
                'amount' => $settings->trial_price * $n, // 当日払い
                'contact_name' => $input['contact']['name'],
                'contact_phone' => $input['contact']['phone'] ?? null,
                'created_by' => $staff->id,
            ]);
        });
    }

    /** 月謝の生徒の代理入力：同じ日に2回・月の回数超え・月謝の残席なし は入れない。4回そろったら提出済みにする */
    private function createMonthly(Slot $slot, $holding, array $input, Channel $channel, User $staff, Setting $settings, CarbonImmutable $now): Reservation
    {
        $member = Member::query()->where('active', true)->find($input['memberId']);
        if (! $member) {
            throw ValidationException::withMessages(['memberId' => '生徒さんを選んでください']);
        }
        $month = JaDate::monthOf($slot->dateString());
        $sessions = $member->sessionsIn($month);
        if ($sessions->contains(fn (Reservation $r) => $r->slot->dateString() === $slot->dateString())) {
            throw ValidationException::withMessages(['slotId' => 'この生徒さんは同じ日にもう予約があります']);
        }
        if ($sessions->count() >= $settings->sessions_per_month) {
            throw ValidationException::withMessages(['memberId' => "この月はもう{$settings->sessions_per_month}回入っています。日程を移すときは日程変更で"]);
        }
        if (! Seats::canBook($slot, $holding, ReservationKind::Monthly, 1, $now)) {
            throw ValidationException::withMessages(['slotId' => self::FULL]);
        }

        $reservation = Reservation::query()->create([
            'code' => Reservation::newCode($now),
            'slot_id' => $slot->id,
            'kind' => ReservationKind::Monthly,
            'channel' => $channel,
            'party_size' => 1,
            'status' => ReservationStatus::Confirmed,
            'amount' => 0,
            'member_id' => $member->id,
            'created_by' => $staff->id,
        ]);

        $mm = $member->monthOf($month);
        if ($sessions->count() + 1 === $settings->sessions_per_month && $mm->submitted_at === null) {
            $mm->submitted_at = $now;
            $mm->save();
        }

        return $reservation;
    }
}
