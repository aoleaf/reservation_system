<?php

namespace Tests\Feature\Member;

use App\Enums\UserRole;
use App\Mail\MemberLoginLink;
use App\Models\Member;
use App\Models\MemberLoginToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberLoginTest extends TestCase
{
    use RefreshDatabase;

    private function linkFor(Member $m): string
    {
        $url = null;
        Mail::fake();
        $this->post('/member/login', ['email' => $m->email])->assertRedirect('/member/login');
        Mail::assertSent(MemberLoginLink::class, function (MemberLoginLink $mail) use (&$url, $m) {
            $url = $mail->url;

            return $mail->hasTo($m->email);
        });

        return parse_url($url, PHP_URL_PATH);
    }

    public function test_登録されたメールにリンクを送り_画面は送りましたと出す(): void
    {
        $m = Member::factory()->create(['email' => 'hanako@example.com']);
        $this->linkFor($m);
        $this->get('/member/login')->assertInertia(fn (Assert $p) => $p->component('member/login')->where('sent', true)->where('email', 'hanako@example.com'));
    }

    public function test_登録されていないメールでも同じ表示_メールは送らない(): void
    {
        Mail::fake();
        $this->post('/member/login', ['email' => 'nobody@example.com'])->assertRedirect('/member/login');
        Mail::assertNothingSent();
        $this->get('/member/login')->assertInertia(fn (Assert $p) => $p->where('sent', true));
    }

    public function test_リンクを開いただけではログインしない_ボタンでログインする(): void
    {
        $m = Member::factory()->create();
        $path = $this->linkFor($m);

        $this->get($path)->assertInertia(fn (Assert $p) => $p->component('member/login-confirm')->where('valid', true));
        $this->assertGuest();

        $this->post($path)->assertRedirect('/member');
        $this->assertAuthenticated();
        $user = User::query()->where('email', $m->email)->sole();
        $this->assertSame(UserRole::Member, $user->role);
        $this->assertSame($user->id, $m->fresh()->user_id);
    }

    public function test_リンクは1回だけ使える(): void
    {
        $m = Member::factory()->create();
        $path = $this->linkFor($m);
        $this->post($path)->assertRedirect('/member');
        $this->post('/logout');

        $this->post($path)->assertRedirect('/member/login')->assertSessionHasErrors('token');
        $this->assertGuest();
        $this->get($path)->assertInertia(fn (Assert $p) => $p->where('valid', false));
    }

    public function test_リンクは15分で切れる(): void
    {
        $m = Member::factory()->create();
        $path = $this->linkFor($m);
        CarbonImmutable::setTestNow(now()->addMinutes(16));
        $this->post($path)->assertSessionHasErrors('token');
        $this->assertGuest();
        CarbonImmutable::setTestNow();
    }

    public function test_同じメールへの送信は10分に3回まで(): void
    {
        $m = Member::factory()->create();
        Mail::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/member/login', ['email' => $m->email]);
        }
        Mail::assertSentCount(3);
        $this->assertSame(3, MemberLoginToken::query()->count());
    }

    public function test_DB_にはトークンのハッシュだけ(): void
    {
        $m = Member::factory()->create();
        $path = $this->linkFor($m);
        $token = basename($path);
        $this->assertFalse(MemberLoginToken::query()->where('token_hash', $token)->exists());
        $this->assertTrue(MemberLoginToken::query()->where('token_hash', hash('sha256', $token))->exists());
    }

}
