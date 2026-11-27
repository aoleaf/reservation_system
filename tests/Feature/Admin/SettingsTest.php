<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** 料金の設定：新しい料金はこれからの予約から。もう入っている予約の金額は変わらない */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_料金を変えると_これからの予約だけが新しい料金になる(): void
    {
        CarbonImmutable::setTestNow('2026-11-26 10:00');
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $slot = Slot::factory()->weekend('2026-11-29')->create();
        $old = Reservation::factory()->for($slot)->create(['amount' => 4500]);

        $this->actingAs($staff)->get('/admin/settings')->assertInertia(fn (Assert $p) => $p->component('admin/settings')->where('trialPrice', 4500));
        $this->actingAs($staff)->patch('/admin/settings', ['trialPrice' => 5000])->assertSessionHasNoErrors();
        $this->assertSame(5000, Setting::current()->trial_price);

        $this->actingAs($staff)->post('/admin/reservations', ['kind' => 'trial', 'slotId' => $slot->id, 'channel' => 'phone', 'partySize' => 2, 'contact' => ['name' => 'a']]);
        $this->assertSame(10000, Reservation::query()->latest('id')->first()->amount);
        $this->assertSame(4500, $old->fresh()->amount);
        $this->get('/reserve/form?slot='.$slot->id)->assertInertia(fn (Assert $p) => $p->where('unitPrice', 5000));
        CarbonImmutable::setTestNow();
    }

    public function test_おかしな料金は受け付けない_スタッフだけ(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        foreach ([0, 99, 100001, 'abc', 4500.5] as $bad) {
            $this->actingAs($staff)->patch('/admin/settings', ['trialPrice' => $bad])->assertSessionHasErrors('trialPrice');
        }
        $this->assertSame(4500, Setting::current()->trial_price);
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))->patch('/admin/settings', ['trialPrice' => 1000])->assertForbidden();
    }
}
