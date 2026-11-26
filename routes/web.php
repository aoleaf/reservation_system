<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Member;
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

// お客様：体験予約
Route::get('/reserve', fn (Request $r) => Inertia::render('reserve/index', [
    'month' => $r->query('month'),
]))->name('reserve.index');

Route::get('/reserve/form', fn (Request $r) => Inertia::render('reserve/form', [
    'slotId' => $r->query('slot'),
    'n' => $r->query('n'),
    'name' => $r->query('name'),
    'phone' => $r->query('phone'),
    'email' => $r->query('email'),
]))->name('reserve.form');

Route::get('/reserve/confirm', fn (Request $r) => Inertia::render('reserve/confirm', [
    'slotId' => $r->query('slot'),
    'n' => $r->query('n'),
    'name' => $r->query('name'),
    'phone' => $r->query('phone'),
    'email' => $r->query('email'),
]))->name('reserve.confirm');

Route::get('/reserve/complete/{id}', fn (string $id) => Inertia::render('reserve/complete', [
    'reservationId' => $id,
]))->name('reserve.complete');

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

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
