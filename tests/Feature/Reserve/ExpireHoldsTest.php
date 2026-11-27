<?php

namespace Tests\Feature\Reserve;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireHoldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_期限が切れた仮押さえだけを戻す(): void
    {
        CarbonImmutable::setTestNow('2026-11-26 10:00');
        $slot = Slot::factory()->weekend('2026-11-29')->create();
        $old = Reservation::factory()->for($slot)->pending(CarbonImmutable::parse('2026-11-26 09:59'))->create();
        $live = Reservation::factory()->for($slot)->pending(CarbonImmutable::parse('2026-11-26 10:30'))->create();
        $done = Reservation::factory()->for($slot)->create(); // confirmed

        $this->artisan('reservations:expire-holds')->expectsOutputToContain('1 件')->assertSuccessful();
        $this->assertSame(ReservationStatus::Expired, $old->fresh()->status);
        $this->assertSame(ReservationStatus::Pending, $live->fresh()->status);
        $this->assertSame(ReservationStatus::Confirmed, $done->fresh()->status);
        CarbonImmutable::setTestNow();
    }
}
