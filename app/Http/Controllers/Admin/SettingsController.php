<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 設定（AdminSettingsProps）。今は体験の料金だけを変えられる（締め日などは画面を作らない：11/26 の決定）。
 * 新しい料金は、これから入る予約から。もう入っている予約の金額は予約ごとに記録しているので変わらない。
 */
class SettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/settings', ['trialPrice' => Setting::current()->trial_price]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(
            ['trialPrice' => ['required', 'integer', 'min:100', 'max:100000']],
            ['trialPrice.*' => '料金は 100円〜100,000円 の整数で入れてください'],
        );
        Setting::current()->update(['trial_price' => $data['trialPrice']]);

        return back();
    }
}
