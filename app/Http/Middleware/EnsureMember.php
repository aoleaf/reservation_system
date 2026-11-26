<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** 生徒さんだけが通れる（/member 以下）。ログインしていなければ、生徒用のログイン画面へ */
class EnsureMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->guest(route('member.login'));
        }
        abort_unless($user->member()->where('active', true)->exists(), 403, '生徒さんだけが使える画面です');

        return $next($request);
    }
}
