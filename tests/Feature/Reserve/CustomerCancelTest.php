<?php

namespace Tests\Feature\Reserve;

use App\Enums\CanceledBy;
use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Mail\CancellationReceipt;
use App\Models\Reservation;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** お客様のキャンセル用リンク（今日 11/26） */
class CustomerCancelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-26 10:00');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** @return array{0: Reservation, 1: string} */
    private function booked(string $date, array $attrs = []): array
    {
        $r = Reservation::factory()->for(Slot::factory()->weekend($date)->create())->create($attrs + ['party_size' => 2, 'amount' => 9000, 'stripe_payment_intent_id' => 'pi_1']);
        $token = $r->issueCancelToken();
        $r->save();

        return [$r, $token];
    }

    public function test_3日前_手数料を引いて返金_メールを送る(): void
    {
        [$r, $token] = $this->booked('2026-11-29');
        $this->get("/r/{$token}/cancel")->assertInertia(fn (Assert $p) => $p
            ->component('reserve/cancel')->where('reservation.id', $r->code)->where('refund', 8676)->where('refundReason', '2日前までのため、決済手数料を引いて返金します')->where('reservation.canceled', false)
        );
        $this->post("/r/{$token}/cancel")->assertRedirect("/r/{$token}/cancel");
        $r->refresh();
        $this->assertSame(ReservationStatus::Canceled, $r->status);
        $this->assertSame(CanceledBy::Customer, $r->canceled_by);
        $this->assertSame(8676, $r->refunded_amount);
        $this->assertSame(RefundStatus::Succeeded, $r->refund_status);
        Mail::assertQueued(CancellationReceipt::class, fn ($m) => $m->hasTo($r->contact_email));
        $this->get("/r/{$token}/cancel")->assertInertia(fn (Assert $p) => $p->where('reservation.canceled', true)->where('reservation.refundedAmount', 8676));
    }

    public function test_前日は半額_当日は0円(): void
    {
        [, $t1] = $this->booked('2026-11-27');
        $this->get("/r/{$t1}/cancel")->assertInertia(fn (Assert $p) => $p->where('refund', 4500));
        [$r2, $t2] = $this->booked('2026-11-26');
        $this->get("/r/{$t2}/cancel")->assertInertia(fn (Assert $p) => $p->where('refund', 0)->where('refundReason', '当日のため、返金はありません'));
        $this->post("/r/{$t2}/cancel");
        $this->assertSame(RefundStatus::None, $r2->fresh()->refund_status);
    }

    public function test_二度目のキャンセルはできない(): void
    {
        [, $token] = $this->booked('2026-11-29');
        $this->post("/r/{$token}/cancel");
        $this->post("/r/{$token}/cancel")->assertSessionHasErrors(['reservationId' => 'この予約はキャンセル済みです']);
        Mail::assertQueuedCount(1);
    }

    public function test_違うトークン_レッスンの日を過ぎたリンクは使えない(): void
    {
        $this->get('/r/wrong/cancel')->assertInertia(fn (Assert $p) => $p->where('reservation', null));
        $this->post('/r/wrong/cancel')->assertNotFound();
        [, $old] = $this->booked('2026-11-22');
        $this->get("/r/{$old}/cancel")->assertInertia(fn (Assert $p) => $p->where('reservation', null));
    }

    public function test_支払いの記録が無いと返金失敗にしてスタッフに分かるようにする(): void
    {
        [$r, $token] = $this->booked('2026-11-29', ['stripe_payment_intent_id' => null]);
        $this->post("/r/{$token}/cancel");
        $this->assertSame(RefundStatus::Failed, $r->fresh()->refund_status);
    }
}
