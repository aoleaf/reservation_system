<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\MemberLoginLink;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Slot;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\Support\ContactFormat;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** メールアドレスと電話番号の形を、各画面のサーバー側で確かめる */
class ContactValidationTest extends TestCase
{
    use RefreshDatabase;

    private Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-26 10:00');
        $this->app->instance(PaymentGateway::class, new FakePaymentGateway);
        $this->slot = Slot::factory()->weekend('2026-11-29')->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function pay(string $phone, string $email): \Illuminate\Testing\TestResponse
    {
        return $this->post('/reserve', ['slotId' => $this->slot->id, 'partySize' => 1, 'contact' => ['name' => '青木 葵', 'phone' => $phone, 'email' => $email]], ['X-Inertia' => 'true']);
    }

    public function test_お客様_形の違うメールと電話は支払いに進めない(): void
    {
        $this->pay('090-1234-5678', 'abc')->assertSessionHasErrors(['contact.email' => ContactFormat::EMAIL_MESSAGE]);
        $this->pay('090-1234-5678', 'aoi@example')->assertSessionHasErrors('contact.email');
        $this->pay('12345', 'aoi@example.com')->assertSessionHasErrors(['contact.phone' => ContactFormat::PHONE_MESSAGE]);
        $this->assertSame(0, Reservation::query()->count());
    }

    public function test_お客様_全角で入れても半角にそろえて受け付ける(): void
    {
        $this->pay('０９０－１２３４ー５６７８', 'Ａｏｉ＠Ｅｘａｍｐｌｅ．ｃｏｍ')->assertStatus(409);
        $r = Reservation::query()->sole();
        $this->assertSame('090-1234-5678', $r->contact_phone);
        $this->assertSame('aoi@example.com', $r->contact_email);
    }

    public function test_確認画面でも形の違いを知らせる(): void
    {
        $base = "/reserve/confirm?slot={$this->slot->id}&n=1&name=a";
        $this->get("{$base}&phone=090-1234-5678&email=abc")->assertInertia(fn (Assert $p) => $p->where('contactError', ContactFormat::EMAIL_MESSAGE));
        $this->get("{$base}&phone=123&email=a@example.com")->assertInertia(fn (Assert $p) => $p->where('contactError', ContactFormat::PHONE_MESSAGE));
        $this->get("{$base}&phone=090-1234-5678&email=a@example.com")->assertInertia(fn (Assert $p) => $p->where('contactError', null));
    }

    public function test_スタッフ_電話は空でもよいが_入れたら形を確かめる(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $post = fn (?string $phone) => $this->actingAs($staff)->post('/admin/reservations', ['kind' => 'trial', 'slotId' => $this->slot->id, 'channel' => 'phone', 'partySize' => 1, 'contact' => ['name' => 'a', 'phone' => $phone]]);
        $post('090-12')->assertSessionHasErrors(['contact.phone' => ContactFormat::PHONE_MESSAGE]);
        $post('')->assertSessionHasNoErrors();
        $post('０３－１２３４－５６７８')->assertSessionHasNoErrors();
        $this->assertSame([null, '03-1234-5678'], Reservation::query()->orderBy('id')->pluck('contact_phone')->all());
    }

    public function test_生徒のログイン_形の違うメールは送らない(): void
    {
        Mail::fake();
        Member::factory()->create(['email' => 'hanako@example.com']);
        $this->post('/member/login', ['email' => 'hanako'])->assertSessionHasErrors(['email' => ContactFormat::EMAIL_MESSAGE]);
        $this->post('/member/login', ['email' => 'ＨＡＮＡＫＯ＠example.com'])->assertSessionHasNoErrors();
        Mail::assertSentCount(1);
        Mail::assertSent(MemberLoginLink::class, fn ($m) => $m->hasTo('hanako@example.com'));
    }

    public function test_CSV_電話の先頭の0が消えていたら教える(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "名前,電話番号,メールアドレス\n佐藤 陽子,9011112222,yoko@example.com\n鈴木 美咲,０９０－３３３３－４４４４,MISAKI@example.com\n");
        $this->artisan('import:members', ['file' => $path])->expectsOutputToContain('2行目：電話番号「9011112222」の形が違います（Excel で先頭の 0 が消えていないか確かめてください）')->assertFailed();

        file_put_contents($path, "名前,電話番号,メールアドレス\n鈴木 美咲,０９０－３３３３－４４４４,MISAKI@example.com\n");
        $this->artisan('import:members', ['file' => $path])->assertSuccessful();
        $m = Member::query()->sole();
        $this->assertSame(['090-3333-4444', 'misaki@example.com'], [$m->phone, $m->email]);
    }
}
