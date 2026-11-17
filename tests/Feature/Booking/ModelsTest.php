<?php

namespace Tests\Feature\Booking;

use App\Enums\ReservationStatus;
use App\Enums\SlotStatus;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_は無ければ初期値で1行だけ作る(): void
    {
        $this->assertSame(4500, Setting::current()->trial_price);
        $this->assertSame(60, Setting::current()->checkout_hold_minutes);
        $this->assertSame(1, Setting::query()->count());
    }

    public function test_枠は日付と開始時刻で一意(): void
    {
        Slot::factory()->create();
        $this->expectException(QueryException::class);
        Slot::factory()->create();
    }

    public function test_枠の時刻は_FE_と同じ形で取り出せる(): void
    {
        $slot = Slot::factory()->create(['start_time' => '15:30', 'end_time' => '17:30'])->fresh();
        $this->assertSame('15:30', $slot->startHm());
        $this->assertSame('17:30', $slot->endHm());
        $this->assertSame('2026-12-01', $slot->dateString());
        $this->assertSame(SlotStatus::Open, $slot->status);
    }

    public function test_枠の日付は_YYYY_MM_DD_で保存し_その形で探せる(): void
    {
        Slot::factory()->create(['date' => '2026-12-02']);
        $this->assertSame('2026-12-02', \DB::table('slots')->value('date')); // DB の中身をそのまま見る
        $this->assertTrue(Slot::query()->where('date', '2026-12-02')->exists());
    }

    public function test_席を使っている予約は_confirmed_と期限内の_pending_だけ(): void
    {
        $now = CarbonImmutable::parse('2026-11-20 12:00');
        $slot = Slot::factory()->create();
        Reservation::factory()->for($slot)->create(); // confirmed
        Reservation::factory()->for($slot)->pending($now->addMinutes(10))->create(); // 期限内
        Reservation::factory()->for($slot)->pending($now->subMinute())->create(); // 期限切れ
        Reservation::factory()->for($slot)->status(ReservationStatus::Canceled)->create();
        Reservation::factory()->for($slot)->status(ReservationStatus::Expired)->create();

        $this->assertSame(2, $slot->holdingReservations($now)->count());
        $this->assertTrue($slot->hasHoldingReservations($now));
    }

    public function test_予約番号は年月と見間違えにくい6文字(): void
    {
        $code = Reservation::newCode(CarbonImmutable::parse('2026-12-03'));
        $this->assertMatchesRegularExpression('/^2612-[A-HJKMNP-Z2-9]{6}$/', $code);
    }

    public function test_キャンセル用トークンはハッシュだけを保存し_トークンで探せる(): void
    {
        $r = Reservation::factory()->create();
        $token = $r->issueCancelToken();
        $r->save();

        $this->assertNotSame($token, $r->fresh()->cancel_token_hash);
        $this->assertSame(64, strlen($r->fresh()->cancel_token_hash));
        $this->assertSame($r->id, Reservation::findByCancelToken($token)?->id);
        $this->assertNull(Reservation::findByCancelToken('wrong'));
        $this->assertArrayNotHasKey('cancel_token_hash', $r->toArray());
    }

    public function test_生徒の月ごとの記録は無ければ未保存の新しい行(): void
    {
        $mm = Member::factory()->create()->monthOf('2026-12');
        $this->assertFalse($mm->exists);
        $this->assertSame(1, $mm->changeRemaining());
    }
}
