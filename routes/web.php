<?php

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
Route::get('/reserve', fn () => Inertia::render('reserve/index'))->name('reserve.index');

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

// 生徒さん
Route::get('/member', fn () => Inertia::render('member/index'))->name('member.index');

Route::get('/member/schedule/{month}', fn (string $month) => Inertia::render('member/schedule', [
    'month' => $month,
]))->name('member.schedule');

Route::get('/member/change/{id}', fn (string $id) => Inertia::render('member/change', [
    'reservationId' => $id,
]))->name('member.change');

// スタッフ
Route::get('/admin', fn (Request $r) => Inertia::render('admin/today', [
    'date' => $r->query('date'),
]))->name('admin.today');

Route::get('/admin/reservations/new', fn (Request $r) => Inertia::render('admin/reservation-new', [
    'date' => $r->query('date'),
    'slotId' => $r->query('slot'),
]))->name('admin.reservations.new');

Route::get('/admin/slots', fn (Request $r) => Inertia::render('admin/slots', [
    'month' => $r->query('month'),
]))->name('admin.slots');

Route::get('/admin/members', fn (Request $r) => Inertia::render('admin/members', [
    'filter' => $r->query('filter'),
]))->name('admin.members');

Route::get('/admin/members/{id}', fn (string $id) => Inertia::render('admin/member-show', [
    'memberId' => $id,
]))->name('admin.members.show');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
