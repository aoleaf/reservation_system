<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\Seats;
use App\Enums\ReservationKind;
use App\Enums\SlotStatus;
use App\Enums\UserRole;
use App\Models\Member;
use App\Models\MemberMonth;
use App\Models\Reservation;
use App\Models\Slot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\BookingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 開発用のデータが、モック（tests/fe/seed.test.ts）と同じ性質を持つことを確かめる */
class BookingDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_モックの_seed_と同じ性質のデータができる(): void
    {
        $this->seed(BookingDemoSeeder::class);
        $now = CarbonImmutable::parse('2026-11-12 12:00');
        $slot = fn (string $date, string $start) => Slot::query()->where('date', $date)->where('start_time', $start)->firstOrFail();
        $all = Reservation::query()->get();

        // 生徒40名、12月の提出は25名、m01（最初の生徒）は未提出
        $this->assertSame(40, Member::query()->count());
        $this->assertSame(25, MemberMonth::query()->where('month', '2026-12')->whereNotNull('submitted_at')->count());
        $m01 = Member::query()->orderBy('id')->first();
        $this->assertNull($m01->monthOf('2026-12')->submitted_at);

        // 11月は全員4回
        foreach (Member::query()->get() as $m) {
            $this->assertSame(4, $m->reservations()->whereHas('slot', fn ($q) => $q->where('date', 'like', '2026-11-%'))->count());
        }

        // 定員を超える枠がない・お休みの枠に予約がない・土日の月謝は3名まで
        foreach (Slot::query()->get() as $s) {
            $this->assertLessThanOrEqual($s->capacity, Seats::used($s, $all, null, $now));
            if ($s->status === SlotStatus::Closed) {
                $this->assertSame(0, Seats::used($s, $all, null, $now));
            }
            if ($s->trial_open) {
                $this->assertLessThanOrEqual(3, Seats::used($s, $all, ReservationKind::Monthly, $now));
            }
        }

        // 11/21 13:00 は月謝3＋体験3で満席
        $full = $slot('2026-11-21', '13:00');
        $this->assertSame(3, Seats::used($full, $all, ReservationKind::Monthly, $now));
        $this->assertSame(3, Seats::used($full, $all, ReservationKind::Trial, $now));

        // 12/12 10:00 は受付停止で、月謝3名が残っている
        $paused = $slot('2026-12-12', '10:00');
        $this->assertSame(SlotStatus::Paused, $paused->status);
        $this->assertSame(3, Seats::used($paused, $all, ReservationKind::Monthly, $now));

        // デモの体験予約は、キャンセル用トークン demo で探せる（11/22 10:00・2名・9,000円）
        $demo = Reservation::findByCancelToken('demo');
        $this->assertSame('2026-11-22', $demo->slot->dateString());
        $this->assertSame(2, $demo->party_size);
        $this->assertSame(9000, $demo->amount);

        // 開発用のスタッフ
        $this->assertSame(UserRole::Staff, User::query()->where('email', 'staff@example.com')->first()->role);
    }
}
