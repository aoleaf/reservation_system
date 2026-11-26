<?php

namespace App\Http\Controllers\Member;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\MemberLoginLink;
use App\Models\Member;
use App\Models\MemberLoginToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 生徒さんのログイン（メールのリンク・パスワードなし）。
 * ・登録されていないメールアドレスでも同じ表示にする（生徒かどうかを当てられないように）
 * ・リンクを開いただけではログインしない。開いた画面のボタン（POST）でログインする
 */
class LoginController extends Controller
{
    /** メールアドレスを入れる画面（MemberLoginProps） */
    public function create(Request $request): Response
    {
        return Inertia::render('member/login', [
            'sent' => (bool) $request->session()->get('member_login_sent'),
            'email' => (string) $request->session()->get('member_login_email', ''),
            'minutes' => MemberLoginToken::LIFETIME_MINUTES,
        ]);
    }

    /** リンクを送る。同じメールアドレスへは10分に3回まで */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']], ['email.*' => 'メールアドレスを入力してください']);
        $email = Str::lower(trim($data['email']));
        $key = 'member-login:'.hash('sha256', $email);

        if (! RateLimiter::tooManyAttempts($key, 3)) {
            RateLimiter::hit($key, 600);
            $member = Member::query()->where('active', true)->whereRaw('lower(email) = ?', [$email])->first();
            if ($member) {
                $token = MemberLoginToken::issue($member);
                Mail::to($member->email)->send(new MemberLoginLink($member, route('member.login.confirm', $token)));
            }
        }

        return redirect()->route('member.login')->with(['member_login_sent' => true, 'member_login_email' => $email]);
    }

    /** リンクを開いた画面（MemberLoginConfirmProps）。ここではまだログインしない */
    public function confirm(string $token): Response
    {
        return Inertia::render('member/login-confirm', [
            'token' => $token,
            'valid' => MemberLoginToken::findUsable($token) !== null,
        ]);
    }

    /** 「ログインする」を押したとき。トークンを使い切ってログインする */
    public function consume(Request $request, string $token): RedirectResponse
    {
        $user = DB::transaction(function () use ($token) {
            $row = MemberLoginToken::findUsable($token);
            if (! $row) {
                return null;
            }
            $row->update(['used_at' => now()]);
            $member = $row->member;
            $user = $member->user ?? User::query()->create([
                'name' => $member->name,
                'email' => $member->email,
                'password' => Str::random(64), // 使わない（メールのリンクでログインする）
                'role' => UserRole::Member,
            ]);
            if ($member->user_id !== $user->id) {
                $member->update(['user_id' => $user->id]);
            }

            return $user;
        });

        if (! $user) {
            return redirect()->route('member.login')->withErrors(['token' => 'このリンクは使えません（期限切れ、または使用済み）。もう一度リンクを送ってください']);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('member.index'));
    }
}
