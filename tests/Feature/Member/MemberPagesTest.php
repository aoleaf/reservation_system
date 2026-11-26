<?php

namespace Tests\Feature\Member;

use App\Enums\ReservationStatus;
use App\Enums\SlotStatus;
use App\Enums\UserRole;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Slot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** 生徒さんの画面（今日 11/26：12月分は締め後、1月分は受付中） */
class MemberPagesTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-26 10:00');
        $this->user = User::factory()->create(['role' => UserRole::Member]);
        $this->member = Member::factory()->create(['user_id' => $this->user->id, 'name' => '佐藤 陽子']);
        $this->artisan('slots:generate', ['month' => '2026-11']);
        $this->artisan('slots:generate', ['month' => '2026-12']);
        $this->artisan('slots:generate', ['month' => '2027-01']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function slot(string $date, string $start = '10:00'): Slot
    {
        return Slot::query()->where('date', $date)->where('start_time', $start)->firstOrFail();
    }

    private function book(string $date, string $start = '10:00', ?Member $m = null): Reservation
    {
        return Reservation::factory()->for($this->slot($date, $start))->monthly($m ?? $this->member)->create();
    }

    public function test_ログインしていないと生徒用のログイン画面へ(): void
    {
        $this->get('/member')->assertRedirect('/member/login');
    }

    public function test_スタッフは生徒の画面に入れない(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))->get('/member/schedule/2027-01')->assertForbidden();
    }

    public function test_マイページ_今月_来月は締め後_再来月は受付中(): void
    {
        foreach (['2026-11-05', '2026-11-12', '2026-11-27', '2026-11-30'] as $d) {
            $this->book($d);
        }
        $this->member->months()->create(['month' => '2026-11', 'submitted_at' => '2026-10-10 10:00']);

        $this->actingAs($this->user)->get('/member')->assertInertia(fn (Assert $p) => $p
            ->component('member/index')
            ->where('member.name', '佐藤 陽子')
            ->has('months', 3)
            ->where('months.0.label', '今月（2026年11月）')
            ->where('months.0.changeRemaining', 1)
            ->where('months.0.sessions.0.past', true)
            ->where('months.0.sessions.0.canChange', false)
            ->where('months.0.sessions.2.canChange', true) // 11/27 は明日
            ->where('months.1.label', '来月（2026年12月）')
            ->where('months.1.canSchedule', false)
            ->where('months.1.scheduleNote', '受付は11月15日（日）で締め切りました。お店（000-0000-0000）にご連絡ください')
            ->where('months.2.label', '再来月（2027年1月）')
            ->where('months.2.monthLabel', '2027年1月')
            ->where('months.2.canSchedule', true)
            ->where('months.2.scheduleNote', '締切：12月15日（火）')
        );
    }

    public function test_日程を出す_4つちょうどで提出できる(): void
    {
        $ids = array_map(fn ($d) => $this->slot($d)->id, ['2027-01-05', '2027-01-07', '2027-01-12', '2027-01-14']);
        $this->actingAs($this->user)->post('/member/schedule/2027-01', ['slotIds' => array_slice($ids, 0, 3)])->assertSessionHasErrors(['slotIds' => '4つ選んでください']);
        $this->actingAs($this->user)->post('/member/schedule/2027-01', ['slotIds' => $ids])->assertRedirect('/member');
        $this->assertSame(4, $this->member->sessionsIn('2027-01')->count());
        $this->assertNotNull($this->member->monthOf('2027-01')->submitted_at);
        $this->actingAs($this->user)->post('/member/schedule/2027-01', ['slotIds' => $ids])->assertSessionHasErrors(['slotIds' => 'この月はもう提出済みです']);
    }

    public function test_日程を出す_同じ日2つ_満席_お休み_締め後は不可(): void
    {
        $sameDay = [$this->slot('2027-01-05')->id, $this->slot('2027-01-05', '13:00')->id, $this->slot('2027-01-07')->id, $this->slot('2027-01-12')->id];
        $this->actingAs($this->user)->post('/member/schedule/2027-01', ['slotIds' => $sameDay])->assertSessionHasErrors(['slotIds' => '同じ日に2つの枠は選べません']);

        $full = $this->slot('2027-01-09'); // 土曜：月謝は3名まで
        Reservation::factory()->count(3)->for($full)->monthly()->create();
        $this->actingAs($this->user)->post('/member/schedule/2027-01', ['slotIds' => [$full->id, $this->slot('2027-01-05')->id, $this->slot('2027-01-07')->id, $this->slot('2027-01-12')->id]])
            ->assertSessionHasErrors(['slotIds' => '満席になった枠があります。選び直してください']);

        $this->slot('2027-01-14')->update(['status' => SlotStatus::Closed]);
        $this->actingAs($this->user)->post('/member/schedule/2027-01', ['slotIds' => [$this->slot('2027-01-14')->id, $this->slot('2027-01-05')->id, $this->slot('2027-01-07')->id, $this->slot('2027-01-12')->id]])
            ->assertSessionHasErrors('slotIds');

        $dec = array_map(fn ($d) => $this->slot($d)->id, ['2026-12-01', '2026-12-03', '2026-12-04', '2026-12-07']);
        $this->actingAs($this->user)->post('/member/schedule/2026-12', ['slotIds' => $dec])->assertSessionHasErrors(['slotIds' => 'この月の日程の受付期間ではありません。お店にご連絡ください']);
        $this->assertSame(0, $this->member->reservations()->count());
    }

    public function test_日程を選ぶ画面_締め後の月と受付中の月(): void
    {
        $this->actingAs($this->user)->get('/member/schedule/2026-12')->assertInertia(fn (Assert $p) => $p->where('unavailable', '受付は11月15日（日）で締め切りました。お店（000-0000-0000）にご連絡ください'));
        $this->actingAs($this->user)->get('/member/schedule/2027-01')->assertInertia(fn (Assert $p) => $p
            ->component('member/schedule')
            ->where('unavailable', null)
            ->where('required', 4)
            ->where('leadingBlanks', 5) // 2027/1/1 は金曜
            ->has('days', 31)
            ->where('days.4.selectable', true)
        );
    }

    public function test_日程変更_同じ月の空き枠へ_1回使うと0回(): void
    {
        $r = $this->book('2026-11-30');
        $this->book('2026-11-27', '13:00');

        $this->actingAs($this->user)->get("/member/change/{$r->id}")->assertInertia(fn (Assert $p) => $p
            ->where('reservation.label', '11月30日（月） 10:00〜12:00')
            ->where('changeRemaining', 1)
            ->where('blockedReason', null)
            ->where('days.26.selectable', false) // 11/27 はもう予約がある日
            ->where('days.27.selectable', true) // 11/28
        );
        $to = $this->slot('2026-11-28');
        $this->actingAs($this->user)->post("/member/change/{$r->id}", ['toSlotId' => $this->slot('2026-11-27')->id])->assertSessionHasErrors(['toSlotId' => '同じ日にもう予約があります']);
        $this->actingAs($this->user)->post("/member/change/{$r->id}", ['toSlotId' => $to->id])->assertRedirect('/member');

        $this->assertSame(ReservationStatus::Canceled, $r->fresh()->status);
        $this->assertTrue($this->member->monthOf('2026-11')->change_used);
        $this->assertTrue($this->member->sessionsIn('2026-11')->contains(fn ($x) => $x->slot_id === $to->id));

        $other = $this->member->sessionsIn('2026-11')->first(fn ($x) => $x->slot->dateString() === '2026-11-27');
        $this->actingAs($this->user)->post("/member/change/{$other->id}", ['toSlotId' => $this->slot('2026-11-29')->id])->assertSessionHasErrors(['reservationId' => 'この月の日程変更はもう使いました']);
    }

    public function test_日程変更_過去の回と他人の予約は不可(): void
    {
        $past = $this->book('2026-11-20');
        $this->actingAs($this->user)->get("/member/change/{$past->id}")->assertInertia(fn (Assert $p) => $p->where('blockedReason', '日程変更はレッスンの前日までです'));
        $this->actingAs($this->user)->post("/member/change/{$past->id}", ['toSlotId' => $this->slot('2026-11-28')->id])->assertSessionHasErrors('reservationId');

        $others = $this->book('2026-11-30', '10:00', Member::factory()->create());
        $this->actingAs($this->user)->get("/member/change/{$others->id}")->assertInertia(fn (Assert $p) => $p->where('reservation', null));
        $this->actingAs($this->user)->post("/member/change/{$others->id}", ['toSlotId' => $this->slot('2026-11-28')->id])->assertSessionHasErrors(['reservationId' => '予約が見つかりません']);
    }

    public function test_スタッフのキャンセルと代理入力は_生徒の変更回数を使わない(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $r = $this->book('2026-11-30');
        $this->actingAs($staff)->post("/admin/reservations/{$r->id}/cancel", ['reason' => 'shop']);
        $this->actingAs($staff)->post('/admin/reservations', ['kind' => 'monthly', 'slotId' => $this->slot('2026-11-28')->id, 'channel' => 'phone', 'memberId' => $this->member->id]);
        $this->assertFalse($this->member->monthOf('2026-11')->change_used);
        $this->assertSame(1, $this->member->monthOf('2026-11')->changeRemaining());
    }
}
