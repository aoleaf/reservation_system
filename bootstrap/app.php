<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ロードバランサー（AWS の ALB など）の後ろで動かすので、X-Forwarded-* を信用する。
        // これが無いと、HTTPS で来ても HTTP と思い込み、署名付き URL が合わない・回数制限がロードバランサーの IP で数えられる。
        // EC2 にはロードバランサーからしか届かないよう、セキュリティグループで絞ること（docs/release.md）
        $middleware->trustProxies(at: '*');

        // Stripe の通知は、Stripe の署名で確かめるので CSRF の対象外
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);

        $middleware->alias([
            'staff' => \App\Http\Middleware\EnsureStaff::class,
            'member' => \App\Http\Middleware\EnsureMember::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
