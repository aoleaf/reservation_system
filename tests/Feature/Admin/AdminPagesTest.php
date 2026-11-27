<?php

namespace Tests\Feature\Admin;

use App\Enums\Channel;
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

/** スタッフ画面の props が types/pages.ts と同じ形で、モックの builder と同じ値になること */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Member $member;

    private Slot $sat10;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-20 10:00');
        $this->staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->member = Member::factory()->create(['name' => '佐藤 陽子', 'phone' => '090-1111-2222']);
        // 12/5（土）の3枠：10:00 に月謝1・Web 体験2名・電話の体験1名
        $this->sat10 = Slot::factory()->weekend('2026-12-05')->create(['start_time' => '10:00', 'end_time' => '12:00']);
        Slot::factory()->weekend('2026-12-05')->create(['start_time' => '13:00', 'end_time' => '15:00']);
        Slot::factory()->weekend('2026-12-05')->create(['start_time' => '15:30', 'end_time' => '17:30']);
        Reservation::factory()->for($this->sat10)->create(['party_size' => 2, 'amount' => 9000, 'contact_name' => '青木 葵', 'created_at' => '2026-11-10 12:00']);
        Reservation::factory()->for($this->sat10)->create(['channel' => Channel::Phone, 'contact_name' => '前田 蓮', 'created_at' => '2026-11-11 12:00']);
        Reservation::factory()->for($this->sat10)->monthly($this->member)->create(['created_at' => '2026-11-12 12:00']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_予約一覧(): void
    {
        $this->actingAs($this->staff)->get('/admin?date=2026-12-05')->assertInertia(fn (Assert $page) => $page
            ->component('admin/today')
            ->where('label', '12月5日（土）')
            ->where('isToday', false)
            ->where('prevDate', '2026-12-04')
            ->has('slots', 3)
            ->where('slots.0.slot.start', '10:00')
            ->where('slots.0.slot.used', ['monthly' => 1, 'trial' => 3])
            ->where('slots.0.slot.remaining', 2)
            ->where('slots.0.add', ['kind' => 'trial', 'remaining' => 2])
            // 月謝が先、体験は申し込み順。支払いの色分けと返金の見込み
            ->where('slots.0.entries.0.name', '佐藤 陽子')
            ->where('slots.0.entries.0.payment', 'monthly')
            ->where('slots.0.entries.1.payment', 'paid')
            ->where('slots.0.entries.1.cancel.customer.refund', 9000 - (int) round(9000 * 0.036))
            ->where('slots.0.entries.1.cancel.shop.refund', 9000)
            ->where('slots.0.entries.2.payment', 'onsite')
            ->where('slots.0.entries.2.channelLabel', '電話')
            ->where('slots.0.entries.2.cancel.customer.refund', 0)
            ->where('slots.0.entries.2.cancel.shop.refund', 0)
        );
    }

    public function test_カレンダー_2か月とパネル(): void
    {
        $this->actingAs($this->staff)->get('/admin/slots?month=2026-12&date=2026-12-05')->assertInertia(fn (Assert $page) => $page
            ->component('admin/slots')
            ->where('prevMonth', '2026-11')
            ->has('months', 2)
            ->where('months.0.label', '2026年12月')
            ->where('months.0.leadingBlanks', 2) // 12/1 は火曜
            ->has('months.0.days', 31)
            ->where('months.0.days.4.slots.0.usedTotal', 4)
            ->where('months.0.days.4.slots.0.fill', 2)
            ->where('months.0.days.4.slots.0.canClose', false)
            ->where('selected.label', '12月5日（土）')
            ->has('selected.slots.0.entries', 3)
            ->where('selected.dayAction', 'pause')
        );
    }

    public function test_予約入力_月謝の生徒を選んだ状態(): void
    {
        $this->actingAs($this->staff)->get("/admin/reservations/new?kind=monthly&date=2026-12-05&member={$this->member->id}")->assertInertia(fn (Assert $page) => $page
            ->component('admin/reservation-new')
            ->where('kind', 'monthly')
            ->where('initialMemberId', (string) $this->member->id)
            ->where('members.0.count', 1)
            ->where('members.0.dates', ['2026-12-05'])
            ->where('slots.0.remaining', 2) // 土日の月謝は3名まで、月謝1名 → 残り2
            ->where('channels.2.value', 'walkin')
        );
    }

    public function test_予約入力_平日の体験は説明を出す(): void
    {
        Slot::factory()->create(['date' => '2026-12-07']);
        $this->actingAs($this->staff)->get('/admin/reservations/new?date=2026-12-07')->assertInertia(fn (Assert $page) => $page
            ->where('dateNote', '体験レッスンは土曜・日曜だけです。上から日を選んでください')
            ->where('slots.0.bookable', false)
        );
    }

    public function test_生徒一覧と未提出の絞り込み(): void
    {
        Member::factory()->create()->months()->create(['month' => '2026-12', 'submitted_at' => '2026-11-05 10:00']);
        $this->actingAs($this->staff)->get('/admin/members?filter=unsubmitted')->assertInertia(fn (Assert $page) => $page
            ->component('admin/members')
            ->where('monthLabel', '2026年12月')
            ->where('counts', ['all' => 2, 'unsubmitted' => 1])
            ->has('members', 1)
            ->where('members.0.name', '佐藤 陽子')
            ->where('members.0.memberNo', $this->member->member_no)
        );
    }

    public function test_生徒の詳細と_見つからないとき(): void
    {
        $this->actingAs($this->staff)->get("/admin/members/{$this->member->id}")->assertInertia(fn (Assert $page) => $page
            ->where('member.name', '佐藤 陽子')
            ->where('member.memberNo', $this->member->member_no)
            ->where('months.1.sessions', ['12月5日（土） 10:00〜12:00'])
        );
        $this->actingAs($this->staff)->get('/admin/members/abc')->assertInertia(fn (Assert $page) => $page->where('member', null)->where('months', []));
    }

    public function test_生徒一覧は会員番号の順_メールの無い生徒さんの詳細(): void
    {
        Member::factory()->create(['member_no' => 'T1000']);
        $noMail = Member::factory()->create(['member_no' => 'T999', 'email' => null]);
        $this->actingAs($this->staff)->get('/admin/members')->assertInertia(fn (Assert $page) => $page
            ->where('members.0.memberNo', $this->member->member_no)
            ->where('members.1.memberNo', 'T999')
            ->where('members.2.memberNo', 'T1000')
        );
        $this->actingAs($this->staff)->get("/admin/members/{$noMail->id}")->assertInertia(fn (Assert $page) => $page
            ->where('member.memberNo', 'T999')
            ->where('member.email', null)
        );
    }

    public function test_お休みの日(): void
    {
        Slot::factory()->create(['date' => '2026-12-02', 'status' => SlotStatus::Closed]);
        $this->actingAs($this->staff)->get('/admin?date=2026-12-02')->assertInertia(fn (Assert $page) => $page->where('closed', true)->where('slots.0.add', null));
    }

    public function test_不正な日付は今日にする(): void
    {
        $this->actingAs($this->staff)->get('/admin?date=2026-02-30')->assertInertia(fn (Assert $page) => $page->where('date', '2026-11-20')->where('isToday', true));
    }
}
