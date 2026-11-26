<?php

namespace Tests\Feature\Line;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\User;
use App\Services\Line\LineMessenger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 朝のまとめのあとに入った、今日の予約だけを追加で送る */
class AddedReservationTest extends TestCase
{
    use RefreshDatabase;

    private FakeLine $line;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->line = new FakeLine;
        $this->app->instance(LineMessenger::class, $this->line);
        Setting::current()->update(['line_group_id' => 'Cshop']);
        $this->staff = User::factory()->create(['role' => UserRole::Staff]);
        Slot::factory()->weekend('2026-11-28')->create(['start_time' => '13:00', 'end_time' => '15:00']);
        Slot::factory()->weekend('2026-11-29')->create(['start_time' => '13:00', 'end_time' => '15:00']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function book(string $date, string $name): void
    {
        $slot = Slot::query()->where('date', $date)->firstOrFail();
        $this->actingAs($this->staff)->post('/admin/reservations', ['kind' => 'trial', 'slotId' => $slot->id, 'channel' => 'walkin', 'partySize' => 2, 'contact' => ['name' => $name]])->assertSessionHasNoErrors();
    }

    public function test_朝のまとめより前の今日の予約は追加で送らない_まとめに入る(): void
    {
        CarbonImmutable::setTestNow('2026-11-28 07:30');
        $this->book('2026-11-28', '早い 予約');
        $this->assertCount(0, $this->line->sent);
        CarbonImmutable::setTestNow('2026-11-28 08:00');
        $this->artisan('line:daily-digest');
        $this->assertStringContainsString('早い 予約', $this->line->sent[0]['text']);
        $this->assertCount(1, $this->line->sent);
    }

    public function test_朝のまとめのあとに入った今日の予約は1通送る(): void
    {
        CarbonImmutable::setTestNow('2026-11-28 08:00');
        $this->artisan('line:daily-digest');
        CarbonImmutable::setTestNow('2026-11-28 10:15');
        $this->book('2026-11-28', '来店 次郎');
        $this->assertCount(2, $this->line->sent);
        $this->assertSame("【つちのね】本日の予約が追加されました\n13:00〜15:00 体験 来店 次郎 2名（当日払い・来店）", $this->line->sent[1]['text']);
    }

    public function test_明日の予約_朝のまとめを送っていない日は_追加で送らない(): void
    {
        CarbonImmutable::setTestNow('2026-11-28 08:00');
        $this->artisan('line:daily-digest');
        CarbonImmutable::setTestNow('2026-11-28 10:00');
        $this->book('2026-11-29', '明日 さん');
        $this->assertCount(1, $this->line->sent); // まとめの1通だけ

        CarbonImmutable::setTestNow('2026-11-29 07:00'); // 29日のまとめはまだ
        $this->book('2026-11-29', '朝早い さん');
        $this->assertCount(1, $this->line->sent);
    }

    public function test_キャンセルは送らない(): void
    {
        CarbonImmutable::setTestNow('2026-11-28 08:00');
        $this->artisan('line:daily-digest');
        CarbonImmutable::setTestNow('2026-11-28 09:00');
        $this->book('2026-11-28', '来店 次郎');
        $r = \App\Models\Reservation::query()->latest('id')->first();
        $this->actingAs($this->staff)->post("/admin/reservations/{$r->id}/cancel", ['reason' => 'customer']);
        $this->assertCount(2, $this->line->sent); // まとめと追加の2通だけ
    }
}
