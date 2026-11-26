<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Dev;
use App\Http\Controllers\Member;
use App\Http\Controllers\ReserveController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
| FEモック期のルート。
| サーバーは URL のパラメーターだけを props で渡す。残りの props は
| モック中は resources/js/mocks/props.ts の builder が作り、本番ではコントローラーが作る。
| 予約・生徒・スタッフの画面には auth ミドルウェアを付けない（モック期のみ）。
*/

Route::get('/', fn () => Inertia::render('demo'))->name('demo');

// お客様：体験予約。props はコントローラーが作る（types/pages.ts と同じ形）
Route::get('/reserve', [ReserveController::class, 'index'])->name('reserve.index');
Route::get('/reserve/form', [ReserveController::class, 'form'])->name('reserve.form');
Route::get('/reserve/confirm', [ReserveController::class, 'confirm'])->name('reserve.confirm');
Route::post('/reserve', [ReserveController::class, 'store'])->middleware('throttle:20,10')->name('reserve.store'); // 仮押さえ → 決済へ
Route::get('/reserve/complete/{code}', [ReserveController::class, 'complete'])->name('reserve.complete'); // 署名付き
Route::get('/reserve/abandon/{code}', [ReserveController::class, 'abandon'])->name('reserve.abandon'); // 署名付き・払わずに戻った

Route::get('/r/{token}/cancel', fn (string $token) => Inertia::render('reserve/cancel', [
    'token' => $token,
]))->name('reserve.cancel');

// 生徒さんのログイン（メールのリンク）
Route::prefix('member/login')->name('member.login')->group(function () {
    Route::get('/', [Member\LoginController::class, 'create']);
    Route::post('/', [Member\LoginController::class, 'store'])->middleware('throttle:10,10')->name('.store'); // 同じ IP から10分に10回まで
    Route::get('/{token}', [Member\LoginController::class, 'confirm'])->name('.confirm');
    Route::post('/{token}', [Member\LoginController::class, 'consume'])->middleware('throttle:20,10')->name('.consume');
});

// 生徒さん（メールのリンクでログインした生徒さんだけ）。props はコントローラーが作る
Route::middleware('member')->prefix('member')->name('member.')->group(function () {
    Route::get('/', [Member\MemberController::class, 'index'])->name('index');
    Route::get('/schedule/{month}', [Member\MemberController::class, 'schedule'])->name('schedule');
    Route::post('/schedule/{month}', [Member\MemberController::class, 'submit'])->name('schedule.store');
    Route::get('/change/{id}', [Member\MemberController::class, 'change'])->name('change');
    Route::post('/change/{reservation}', [Member\MemberController::class, 'changeStore'])->name('change.store');
});

// スタッフ（ログインしたスタッフだけ）。props はコントローラーが作る（types/pages.ts と同じ形）
Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\TodayController::class)->name('today');
    Route::get('/reservations/new', [Admin\ReservationController::class, 'create'])->name('reservations.new');
    Route::post('/reservations', [Admin\ReservationController::class, 'store'])->name('reservations.store');
    Route::post('/reservations/{reservation}/cancel', [Admin\ReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::get('/slots', [Admin\SlotController::class, 'index'])->name('slots');
    Route::patch('/slots/{slot}', [Admin\SlotController::class, 'update'])->name('slots.update');
    Route::patch('/days/{date}', [Admin\SlotController::class, 'updateDay'])->name('days.update');
    Route::get('/members', [Admin\MemberController::class, 'index'])->name('members');
    Route::get('/members/{id}', [Admin\MemberController::class, 'show'])->name('members.show');
});

// Stripe の通知（署名で確かめる）
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

// 開発用の偽物の決済画面（Stripe のキーが無い開発環境だけ。本番では作らない）
if (! app()->isProduction()) {
    Route::get('/dev/checkout/{id}', [Dev\DevCheckoutController::class, 'show'])->name('dev.checkout.show');
    Route::post('/dev/checkout/{id}/pay', [Dev\DevCheckoutController::class, 'pay'])->name('dev.checkout.pay');
}

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
