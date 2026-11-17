<?php

namespace Tests\Feature\Booking;

use App\Enums\SlotStatus;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateSlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_1か月分を毎日3枠作り_土日は体験あり月謝3名まで(): void
    {
        $this->artisan('slots:generate', ['month' => '2026-12'])->assertSuccessful();

        $this->assertSame(31 * 3, Slot::query()->count());
        $sat = Slot::query()->whereDate('date', '2026-12-05')->get();
        $this->assertCount(3, $sat);
        $this->assertTrue($sat->every(fn (Slot $s) => $s->trial_open && $s->monthly_limit === 3));
        $mon = Slot::query()->whereDate('date', '2026-12-07')->first();
        $this->assertFalse($mon->trial_open);
        $this->assertNull($mon->monthly_limit);
    }

    public function test_もう一度実行してもお休みにした枠を上書きしない(): void
    {
        $this->artisan('slots:generate', ['month' => '2026-12']);
        Slot::query()->whereDate('date', '2026-12-02')->update(['status' => SlotStatus::Closed]);

        $this->artisan('slots:generate', ['month' => '2026-12'])->expectsOutputToContain('0 枠')->assertSuccessful();
        $this->assertSame(3, Slot::query()->whereDate('date', '2026-12-02')->where('status', 'closed')->count());
    }

    public function test_月を省略すると翌々月(): void
    {
        CarbonImmutable::setTestNow('2026-11-01 03:00');
        $this->artisan('slots:generate')->assertSuccessful();
        $this->assertTrue(Slot::query()->whereDate('date', '2027-01-01')->exists());
        CarbonImmutable::setTestNow();
    }

    public function test_形の違う月は受け付けない(): void
    {
        $this->artisan('slots:generate', ['month' => '2026-13'])->assertFailed();
    }
}
