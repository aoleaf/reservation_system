<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** スタッフだけが通れる（/admin 以下）。ログインしていない人は auth ミドルウェアがログイン画面へ送る */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isStaff(), 403, 'スタッフだけが使える画面です');

        return $next($request);
    }
}
