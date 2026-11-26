<?php

namespace Tests\Feature\Admin;

use App\Enums\Channel;
use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Enums\SlotStatus;
use App\Enums\UserRole;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Slot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** スタッフの書き込み。確かめることは mocks/actions.ts と同じ */
class AdminWritesTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-20 10:00');
        $this->staff = User::factory()->create(['role' => UserRole::Staff]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function trial(Slot $slot, int $n, string $channel = 'phone'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->staff)->post('/admin/reservations', ['kind' => 'trial', 'slotId' => $slot->id, 'channel' => $channel, 'partySize' => $n, 'contact' => ['name' => '電話 花子', 'phone' => '080-1234-5678']]);
    }

    public function test_体験の電話予約を入れると_その日の一覧へ(): void
    {
        $slot = Slot::factory()->weekend()->create();
        $this->trial($slot, 2, 'walkin')->assertRedirect('/admin?date=2026-12-05');

        $r = Reservation::query()->sole();
        $this->assertSame(Channel::Walkin, $r->channel);
        $this->assertSame(9000, $r->amount);
        $this->assertSame($this->staff->id, $r->created_by);
        $this->assertMatchesRegularExpression('/^2611-/', $r->code);
    }

    public function test_満席なら入れられない(): void
    {
        $slot = Slot::factory()->weekend()->create();
        Reservation::factory()->for($slot)->create(['party_size' => 5]);
        $this->trial($slot, 2)->assertSessionHasErrors(['slotId' => '満席になりました。別の枠をお選びください']);
        $this->assertSame(1, Reservation::query()->count());
    }

    public function test_平日や受付停止の枠には体験を入れられない(): void
    {
        $this->trial(Slot::factory()->create(), 1)->assertSessionHasErrors('slotId');
        $this->trial(Slot::factory()->weekend()->status(SlotStatus::Paused)->create(['start_time' => '13:00']), 1)->assertSessionHasErrors('slotId');
    }

    public function test_体験は名前が必要(): void
    {
        $slot = Slot::factory()->weekend()->create();
        $this->actingAs($this->staff)->post('/admin/reservations', ['kind' => 'trial', 'slotId' => $slot->id, 'channel' => 'phone', 'partySize' => 1, 'contact' => ['name' => '']])
            ->assertSessionHasErrors(['contact.name' => 'お名前を入力してください']);
    }

    public function test_月謝の代理入力_同じ日不可_4回目で提出済み_5回目不可(): void
    {
        $m = Member::factory()->create();
        $days = ['2026-12-01', '2026-12-03', '2026-12-04', '2026-12-07', '2026-12-09'];
        $slots = array_map(fn ($d) => Slot::factory()->create(['date' => $d]), $days);
        $post = fn (Slot $s) => $this->actingAs($this->staff)->post('/admin/reservations', ['kind' => 'monthly', 'slotId' => $s->id, 'channel' => 'phone', 'memberId' => $m->id]);

        $post($slots[0])->assertRedirect('/admin?date=2026-12-01');
        $post($slots[0])->assertSessionHasErrors(['slotId' => 'この生徒さんは同じ日にもう予約があります']);
        $post($slots[1]);
        $post($slots[2]);
        $this->assertNull($m->monthOf('2026-12')->submitted_at);
        $post($slots[3]);
        $this->assertNotNull($m->fresh()->monthOf('2026-12')->submitted_at);
        $post($slots[4])->assertSessionHasErrors('memberId');
        $this->assertSame(4, $m->sessionsIn('2026-12')->count());
    }

    public function test_月謝_土日は3名まで(): void
    {
        $slot = Slot::factory()->weekend()->create();
        Reservation::factory()->count(3)->for($slot)->monthly()->create();
        $m = Member::factory()->create();
        $this->actingAs($this->staff)->post('/admin/reservations', ['kind' => 'monthly', 'slotId' => $slot->id, 'channel' => 'dm', 'memberId' => $m->id])
            ->assertSessionHasErrors(['slotId' => '満席になりました。別の枠をお選びください']);
    }

    public function test_キャンセル_Web_の体験は返金額を記録_当日払いは0_二度目は不可(): void
    {
        $slot = Slot::factory()->weekend('2026-11-21')->create(); // 今日 11/20 の前日 → 50%
        $web = Reservation::factory()->for($slot)->create(['party_size' => 2, 'amount' => 9000]);
        $phone = Reservation::factory()->for($slot)->create(['channel' => Channel::Phone]);

        $this->actingAs($this->staff)->from('/admin?date=2026-11-21')->post("/admin/reservations/{$web->id}/cancel", ['reason' => 'customer'])->assertRedirect('/admin?date=2026-11-21');
        $web->refresh();
        $this->assertSame(ReservationStatus::Canceled, $web->status);
        $this->assertSame(4500, $web->refunded_amount);
        $this->assertSame(RefundStatus::Pending, $web->refund_status); // Stripe につなぐまでは記録だけ

        $this->actingAs($this->staff)->post("/admin/reservations/{$phone->id}/cancel", ['reason' => 'shop']);
        $this->assertSame(0, $phone->fresh()->refunded_amount);
        $this->assertSame(RefundStatus::None, $phone->fresh()->refund_status);

        $this->actingAs($this->staff)->post("/admin/reservations/{$web->id}/cancel", ['reason' => 'customer'])->assertSessionHasErrors(['reservationId' => 'この予約はキャンセル済みです']);
    }

    public function test_お店の都合のキャンセルは全額返金_理由を記録(): void
    {
        $slot = Slot::factory()->weekend('2026-11-21')->create(); // 前日でも全額
        $web = Reservation::factory()->for($slot)->create(['party_size' => 2, 'amount' => 9000]);
        $this->actingAs($this->staff)->post("/admin/reservations/{$web->id}/cancel", ['reason' => 'shop'])->assertSessionHasNoErrors();
        $this->assertSame(9000, $web->fresh()->refunded_amount);
        $this->assertSame(\App\Enums\CancelReason::Shop, $web->fresh()->cancel_reason);
        $this->actingAs($this->staff)->post('/admin/reservations/'.Reservation::factory()->for($slot)->create()->id.'/cancel', [])->assertSessionHasErrors('reason');
    }

    public function test_お休みは予約が無い枠だけ(): void
    {
        $busy = Slot::factory()->create();
        Reservation::factory()->for($busy)->monthly()->create();
        $empty = Slot::factory()->create(['start_time' => '13:00']);

        $this->actingAs($this->staff)->patch("/admin/slots/{$busy->id}", ['status' => 'closed'])->assertSessionHasErrors('slotId');
        $this->actingAs($this->staff)->patch("/admin/slots/{$busy->id}", ['status' => 'paused'])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->patch("/admin/slots/{$empty->id}", ['status' => 'closed'])->assertSessionHasNoErrors();
        $this->assertSame(SlotStatus::Paused, $busy->fresh()->status);
        $this->assertSame(SlotStatus::Closed, $empty->fresh()->status);
        $this->actingAs($this->staff)->patch("/admin/slots/{$empty->id}", ['status' => 'xxx'])->assertSessionHasErrors('status');
    }

    public function test_1日まとめて受付停止_お休みの枠はそのまま(): void
    {
        Slot::factory()->create();
        Slot::factory()->create(['start_time' => '13:00', 'status' => SlotStatus::Closed]);
        $this->actingAs($this->staff)->patch('/admin/days/2026-12-01', ['status' => 'paused'])->assertSessionHasNoErrors();
        $this->assertSame(['paused', 'closed'], Slot::query()->orderBy('start_time')->pluck('status')->map->value->all());
        $this->actingAs($this->staff)->patch('/admin/days/2026-12-01', ['status' => 'closed'])->assertSessionHasErrors('status'); // まとめてお休みは不可
        $this->actingAs($this->staff)->patch('/admin/days/2026-12-31', ['status' => 'paused'])->assertSessionHasErrors('date');
    }

    public function test_スタッフでなければ書き込めない(): void
    {
        $slot = Slot::factory()->create();
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))->patch("/admin/slots/{$slot->id}", ['status' => 'closed'])->assertForbidden();
    }
}
