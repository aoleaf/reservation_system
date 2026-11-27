<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** スタッフのログインまわりのエラーとメールが日本語 */
class JapaneseAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_ログインのエラーが日本語(): void
    {
        $u = User::factory()->create(['role' => UserRole::Staff]);
        $this->post('/login', ['email' => $u->email, 'password' => 'wrong'])->assertSessionHasErrors(['email' => 'メールアドレスかパスワードが違います。']);
        $this->post('/login', ['email' => '', 'password' => ''])->assertSessionHasErrors(['email' => 'メールアドレス を入れてください。']);
    }

    public function test_パスワード再設定のメールが日本語(): void
    {
        Notification::fake();
        $u = User::factory()->create(['role' => UserRole::Staff]);
        $this->post('/forgot-password', ['email' => $u->email])->assertSessionHas('status', '登録されているメールアドレスなら、パスワードを設定し直すリンクをお送りします。'); // アカウントの有無で表示を変えない
        Notification::assertSentTo($u, ResetPassword::class, function (ResetPassword $n) use ($u) {
            $mail = $n->toMail($u);

            return $mail->subject === '【つちのね】パスワードの再設定' && $mail->actionText === 'パスワードを設定し直す';
        });
    }
}
