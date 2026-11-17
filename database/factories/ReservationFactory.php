<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\ReservationKind;
use App\Enums\ReservationStatus;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /** 確定した Web の体験予約 1名 */
    public function definition(): array
    {
        return [
            'code' => fn () => Reservation::newCode(),
            'slot_id' => Slot::factory(),
            'kind' => ReservationKind::Trial,
            'channel' => Channel::Web,
            'party_size' => 1,
            'status' => ReservationStatus::Confirmed,
            'amount' => 4500,
            'contact_name' => fake('ja_JP')->name(),
            'contact_phone' => fake('ja_JP')->phoneNumber(),
            'contact_email' => fake()->safeEmail(),
        ];
    }

    /** 月謝の予約（1名・0円） */
    public function monthly(?Member $member = null): static
    {
        return $this->state(fn () => [
            'kind' => ReservationKind::Monthly,
            'channel' => Channel::Member,
            'party_size' => 1,
            'amount' => 0,
            'member_id' => $member?->id ?? Member::factory(),
            'contact_name' => null,
            'contact_phone' => null,
            'contact_email' => null,
        ]);
    }

    /** 仮押さえ（期限を指定） */
    public function pending(\DateTimeInterface $expiresAt): static
    {
        return $this->state(fn () => ['status' => ReservationStatus::Pending, 'expires_at' => $expiresAt]);
    }

    public function status(ReservationStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
