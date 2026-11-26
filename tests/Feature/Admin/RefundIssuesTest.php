<?php

namespace Tests\Feature\Admin;

use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** 返金に失敗した予約を、スタッフが予約一覧で気づき、手で返金したら消す */
class RefundIssuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_返金失敗は日付を問わず予約一覧に出て_返金したを押すと消える(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $failed = Reservation::factory()->for(Slot::factory()->weekend('2026-12-05')->create())->create([
            'status' => ReservationStatus::Canceled, 'refund_status' => RefundStatus::Failed, 'refunded_amount' => 8676, 'contact_name' => '青木 葵', 'canceled_at' => now(),
        ]);

        $this->actingAs($staff)->get('/admin?date=2026-11-20')->assertInertia(fn (Assert $p) => $p
            ->has('refundIssues', 1)
            ->where('refundIssues.0.name', '青木 葵')
            ->where('refundIssues.0.amount', 8676)
            ->where('refundIssues.0.slotLabel', '12月5日（土） 10:00〜12:00')
        );

        $this->actingAs($staff)->post("/admin/reservations/{$failed->id}/refund-resolved")->assertSessionHasNoErrors();
        $this->assertSame(RefundStatus::Manual, $failed->fresh()->refund_status);
        $this->actingAs($staff)->get('/admin')->assertInertia(fn (Assert $p) => $p->has('refundIssues', 0));
        $this->actingAs($staff)->post("/admin/reservations/{$failed->id}/refund-resolved")->assertSessionHasErrors('reservationId');
    }
}
