<?php

namespace Tests\Unit\Domain;

use App\Domain\Booking\Seats;
use App\Enums\Channel;
use App\Enums\ReservationKind;
use App\Enums\ReservationStatus;
use App\Enums\SlotStatus;
use App\Models\Reservation;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/** 残席の計算（DB を使わず、保存していないモデルで確かめる） */
class SeatsTest extends TestCase
{
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-11-20 12:00');
    }

    private function slot(array $attrs = []): Slot
    {
        // 渡した値を優先する（配列の + は左側のキーが勝つ）
        return (new Slot)->forceFill($attrs + ['id' => 1, 'date' => '2026-12-05', 'start_time' => '10:00', 'end_time' => '12:00', 'capacity' => 6, 'trial_open' => true, 'monthly_limit' => 3, 'status' => SlotStatus::Open]);
    }

    private function res(ReservationKind $kind, int $n, ReservationStatus $status = ReservationStatus::Confirmed, ?string $expires = null): Reservation
    {
        return (new Reservation)->forceFill(['slot_id' => 1, 'kind' => $kind, 'channel' => Channel::Web, 'party_size' => $n, 'status' => $status, 'expires_at' => $expires]);
    }

    public function test_使っている席は_confirmed_と期限内の_pending(): void
    {
        $rs = [
            $this->res(ReservationKind::Trial, 2),
            $this->res(ReservationKind::Trial, 1, ReservationStatus::Pending, '2026-11-20 12:30'),
            $this->res(ReservationKind::Trial, 3, ReservationStatus::Pending, '2026-11-20 11:59'), // 期限切れ
            $this->res(ReservationKind::Monthly, 1, ReservationStatus::Canceled),
        ];
        $this->assertSame(3, Seats::used($this->slot(), $rs, null, $this->now));
    }

    public function test_土日は月謝が3名で上限_体験はまだ入る(): void
    {
        $rs = [$this->res(ReservationKind::Monthly, 1), $this->res(ReservationKind::Monthly, 1), $this->res(ReservationKind::Monthly, 1)];
        $this->assertSame(0, Seats::remaining($this->slot(), $rs, ReservationKind::Monthly, $this->now));
        $this->assertSame(3, Seats::remaining($this->slot(), $rs, ReservationKind::Trial, $this->now));
    }

    public function test_平日は月謝の上限なし_体験は受けない(): void
    {
        $slot = $this->slot(['trial_open' => false, 'monthly_limit' => null]);
        $rs = [$this->res(ReservationKind::Monthly, 1), $this->res(ReservationKind::Monthly, 1), $this->res(ReservationKind::Monthly, 1), $this->res(ReservationKind::Monthly, 1)];
        $this->assertSame(2, Seats::remaining($slot, $rs, ReservationKind::Monthly, $this->now));
        $this->assertSame(0, Seats::remaining($slot, $rs, ReservationKind::Trial, $this->now));
    }

    public function test_受付停止とお休みの枠は残席0(): void
    {
        foreach ([SlotStatus::Paused, SlotStatus::Closed] as $status) {
            $this->assertSame(0, Seats::remaining($this->slot(['status' => $status]), [], ReservationKind::Trial, $this->now));
        }
    }

    public function test_満席なら予約できない_人数0も不可(): void
    {
        $rs = [$this->res(ReservationKind::Trial, 4)];
        $this->assertTrue(Seats::canBook($this->slot(), $rs, ReservationKind::Trial, 2, $this->now));
        $this->assertFalse(Seats::canBook($this->slot(), $rs, ReservationKind::Trial, 3, $this->now));
        $this->assertFalse(Seats::canBook($this->slot(), [], ReservationKind::Trial, 0, $this->now));
    }

    public function test_ほかの枠の予約は数えない(): void
    {
        $other = $this->res(ReservationKind::Trial, 6)->forceFill(['slot_id' => 2]);
        $this->assertSame(6, Seats::remaining($this->slot(), [$other], ReservationKind::Trial, $this->now));
    }
}
