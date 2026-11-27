<?php

namespace Tests\Feature\Reserve;

use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Mail\PaymentTooLate;
use App\Mail\ReservationConfirmed;
use App\Models\Reservation;
use App\Models\Slot;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Stripe の通知で確定・期限切れ・遅れて届いた支払い（決済は開発用の偽物。通知の形は Stripe と同じ） */
class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-26 10:00');
        Mail::fake();
        $this->app->instance(PaymentGateway::class, new FakePaymentGateway);
        $this->slot = Slot::factory()->weekend('2026-11-29')->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function pending(int $n = 2, string $expires = '2026-11-26 11:00'): Reservation
    {
        return Reservation::factory()->for($this->slot)->pending(CarbonImmutable::parse($expires))->create(['party_size' => $n, 'amount' => 4500 * $n, 'stripe_checkout_session_id' => 'cs_test_'.uniqid()]);
    }

    private function notify(string $type, Reservation $r, string $eventId = 'evt_1'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/stripe/webhook', [
            'id' => $eventId, 'type' => $type,
            'data' => ['object' => ['id' => $r->stripe_checkout_session_id, 'payment_intent' => 'pi_1', 'payment_status' => 'paid']],
        ]);
    }

    public function test_支払いの通知で確定し_予約確認メールにキャンセル用リンク(): void
    {
        $r = $this->pending();
        $this->notify('checkout.session.completed', $r)->assertOk();
        $r->refresh();
        $this->assertSame(ReservationStatus::Confirmed, $r->status);
        $this->assertSame('pi_1', $r->stripe_payment_intent_id);
        $this->assertNotNull($r->cancel_token_hash);
        Mail::assertQueued(ReservationConfirmed::class, function (ReservationConfirmed $m) use ($r) {
            $token = basename(dirname(parse_url($m->cancelUrl, PHP_URL_PATH)));

            return $m->hasTo($r->contact_email) && Reservation::findByCancelToken($token)?->id === $r->id;
        });
    }

    public function test_同じ通知が2回来ても1回だけ処理する(): void
    {
        $r = $this->pending();
        $this->notify('checkout.session.completed', $r, 'evt_same')->assertOk();
        $hash = $r->fresh()->cancel_token_hash;
        $this->notify('checkout.session.completed', $r, 'evt_same')->assertOk();
        $this->notify('checkout.session.completed', $r, 'evt_other')->assertOk(); // 別の通知でも、確定済みなら何もしない
        Mail::assertQueuedCount(1);
        $this->assertSame($hash, $r->fresh()->cancel_token_hash);
    }

    public function test_期限切れの通知で席を戻す(): void
    {
        $r = $this->pending();
        $this->notify('checkout.session.expired', $r)->assertOk();
        $this->assertSame(ReservationStatus::Expired, $r->fresh()->status);
        $this->assertSame(0, $this->slot->holdingReservations()->count());
    }

    public function test_期限のあとに支払いが届いても_席が空いていれば確定(): void
    {
        $r = $this->pending(2, '2026-11-26 09:00');
        $r->update(['status' => ReservationStatus::Expired]);
        $this->notify('checkout.session.completed', $r)->assertOk();
        $this->assertSame(ReservationStatus::Confirmed, $r->fresh()->status);
        Mail::assertQueued(ReservationConfirmed::class);
    }

    public function test_期限のあとに支払いが届き_席が無ければ全額返金してお知らせ(): void
    {
        $r = $this->pending(2, '2026-11-26 09:00');
        Reservation::factory()->for($this->slot)->create(['party_size' => 5]); // その間に埋まった
        $this->notify('checkout.session.completed', $r)->assertOk();
        $r->refresh();
        $this->assertSame(ReservationStatus::Expired, $r->status);
        $this->assertSame(9000, $r->refunded_amount);
        $this->assertSame(RefundStatus::Succeeded, $r->refund_status);
        Mail::assertQueued(PaymentTooLate::class);
        Mail::assertNotQueued(ReservationConfirmed::class);
    }

    public function test_形の違う通知は_400(): void
    {
        $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], 'not json')->assertStatus(400);
    }

    public function test_開発用の決済画面で支払うと_完了画面が確定になる(): void
    {
        $this->post('/reserve', ['slotId' => $this->slot->id, 'partySize' => 1, 'contact' => ['name' => 'a', 'phone' => '090-1234-5678', 'email' => 'a@example.com']], ['X-Inertia' => 'true']);
        $r = Reservation::query()->sole();
        $this->get("/dev/checkout/{$r->stripe_checkout_session_id}")->assertOk()->assertSee('テストで支払う');
        $success = $this->post("/dev/checkout/{$r->stripe_checkout_session_id}/pay")->assertRedirect()->headers->get('Location');
        $this->assertSame(ReservationStatus::Confirmed, $r->fresh()->status);
        $this->get($success)->assertInertia(fn (Assert $p) => $p->where('paymentStatus', 'confirmed')->where('reservation.id', $r->code));
    }
}
