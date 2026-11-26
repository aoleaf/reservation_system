<?php

namespace Tests\Feature\Reserve;

use App\Enums\ReservationStatus;
use App\Enums\SlotStatus;
use App\Models\Reservation;
use App\Models\Slot;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** お客様の体験予約（今日 11/26・決済は開発用の偽物） */
class ReserveFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-26 10:00');
        $this->app->instance(PaymentGateway::class, new FakePaymentGateway);
        $this->artisan('slots:generate', ['month' => '2026-11']);
        $this->artisan('slots:generate', ['month' => '2026-12']);
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

    private function pay(Slot $slot, int $n = 2, array $contact = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/reserve', ['slotId' => $slot->id, 'partySize' => $n, 'contact' => $contact + ['name' => '青木 葵', 'phone' => '090-1234-5678', 'email' => 'Aoi@Example.com']], ['X-Inertia' => 'true']);
    }

    public function test_一覧は前日までの土日だけ_満席は×(): void
    {
        foreach (['10:00', '13:00', '15:30'] as $t) {
            Reservation::factory()->for($this->slot('2026-11-28', $t))->create(['party_size' => 6]);
        }
        $this->get('/reserve')->assertInertia(fn (Assert $p) => $p
            ->component('reserve/index')
            ->where('monthLabel', '2026年11月')
            ->where('prevMonth', null)
            ->where('nextMonth', '2026-12')
            ->where('days.25.mark', 'none') // 11/26（今日）
            ->where('days.26.mark', 'none') // 11/27（平日）
            ->where('days.27.mark', 'full') // 11/28 満席
            ->where('days.28.mark', 'ok') // 11/29
            ->where('initialDate', '2026-11-29')
        );
    }

    public function test_受付停止だけの日は受付なし(): void
    {
        Slot::query()->where('date', '2026-11-29')->update(['status' => SlotStatus::Paused]);
        $this->get('/reserve')->assertInertia(fn (Assert $p) => $p->where('days.28.mark', 'none'));
    }

    public function test_入力と確認の画面(): void
    {
        $s = $this->slot('2026-11-29');
        $this->get("/reserve/form?slot={$s->id}&n=abc")->assertInertia(fn (Assert $p) => $p
            ->where('slotLabel', '11月29日（日） 10:00〜12:00')->where('maxParty', 6)->has('options', 6)->where('options.1.amount', 9000)->where('initial.partySize', 1)
        );
        $this->get("/reserve/confirm?slot={$s->id}&n=2&name=a&phone=090-1234-5678&email=a@example.com")->assertInertia(fn (Assert $p) => $p
            ->where('amount', 9000)->where('contactError', null)->has('cancelPolicy', 3)
        );
        $this->get("/reserve/confirm?slot={$s->id}&n=2&name=a&phone=090-1234-5678")->assertInertia(fn (Assert $p) => $p->where('contactError', 'お名前・電話番号・メールアドレスを入力してください'));
        $this->get('/reserve/form?slot=999999')->assertInertia(fn (Assert $p) => $p->where('slot', null));
        $this->get('/reserve/form?slot='.$this->slot('2026-11-27')->id)->assertInertia(fn (Assert $p) => $p->where('slot', null)); // 平日
    }

    public function test_支払うと仮押さえを作って決済のページへ(): void
    {
        $s = $this->slot('2026-11-29');
        $res = $this->pay($s);
        $res->assertStatus(409); // Inertia::location（外のページへ移る）
        $r = Reservation::query()->sole();
        $this->assertSame(ReservationStatus::Pending, $r->status);
        $this->assertSame(9000, $r->amount);
        $this->assertSame('aoi@example.com', $r->contact_email);
        $this->assertSame('2026-11-26 11:00:00', $r->expires_at->format('Y-m-d H:i:s')); // 仮押さえは60分
        $this->assertSame("cs_fake_{$r->code}", $r->stripe_checkout_session_id);
        $this->assertStringContainsString("/dev/checkout/cs_fake_{$r->code}", $res->headers->get('X-Inertia-Location'));
        // 仮押さえの間は、その席は使われている
        $this->assertSame(2, (int) $s->holdingReservations()->sum('party_size')); // MySQL は合計を文字列で返す
    }

    public function test_満席なら仮押さえを作らない(): void
    {
        $s = $this->slot('2026-11-29');
        Reservation::factory()->for($s)->pending(now()->addMinutes(10))->create(['party_size' => 5]); // 期限内の仮押さえも数える
        $this->pay($s)->assertSessionHasErrors(['slotId' => '満席になりました。別の枠をお選びください']);
        $this->assertSame(1, Reservation::query()->count());
    }

    public function test_メールが無い_平日の枠は受け付けない(): void
    {
        $this->pay($this->slot('2026-11-29'), 1, ['email' => ''])->assertSessionHasErrors('contact.email');
        $this->pay($this->slot('2026-11-27'), 1)->assertSessionHasErrors(['slotId' => '枠が見つかりません']);
    }

    public function test_払わずに戻ると席を戻す_署名が無いと戻せない(): void
    {
        $this->pay($this->slot('2026-11-29'));
        $r = Reservation::query()->sole();
        $this->get("/reserve/abandon/{$r->code}")->assertForbidden();
        $this->get(URL::temporarySignedRoute('reserve.abandon', now()->addHour(), ['code' => $r->code]))->assertRedirect('/reserve');
        $this->assertSame(ReservationStatus::Expired, $r->fresh()->status);
        $this->assertTrue(FakePaymentGateway::session($r->stripe_checkout_session_id)['expired']);
        $this->get('/reserve')->assertInertia(fn (Assert $p) => $p->where('notice', 'お支払いを中止しました。席の仮押さえを解除しました。'));
    }

    public function test_完了画面は署名付きの_URL_でだけ中身を見せる(): void
    {
        $this->pay($this->slot('2026-11-29'));
        $r = Reservation::query()->sole();
        $this->get("/reserve/complete/{$r->code}")->assertInertia(fn (Assert $p) => $p->where('reservation', null));
        $signed = URL::temporarySignedRoute('reserve.complete', now()->addDay(), ['code' => $r->code]);
        $this->get($signed)->assertInertia(fn (Assert $p) => $p->where('reservation.id', $r->code)->where('paymentStatus', 'pending'));
        $r->update(['status' => ReservationStatus::Confirmed]);
        $this->get($signed)->assertInertia(fn (Assert $p) => $p->where('paymentStatus', 'confirmed')->where('reservation.contact.email', 'aoi@example.com'));
    }
}
