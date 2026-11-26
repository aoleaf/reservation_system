<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Line\LineMessenger;
use App\Support\JaDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * 設定（AdminSettingsProps）。
 * ・体験の料金（新しい料金はこれからの予約から）
 * ・LINE 通知の送り先のグループ（招待されたグループを候補として受け取り、スタッフが決める）とテスト送信
 */
class SettingsController extends Controller
{
    public function edit(): Response
    {
        $s = Setting::current();

        return Inertia::render('admin/settings', [
            'trialPrice' => $s->trial_price,
            'line' => [
                'configured' => (string) config('services.line.channel_access_token') !== '',
                'group' => self::mask($s->line_group_id),
                'candidate' => $s->line_group_candidate_id && $s->line_group_candidate_id !== $s->line_group_id
                    ? ['id' => self::mask($s->line_group_candidate_id), 'at' => $s->line_group_candidate_at ? JaDate::dayLabel($s->line_group_candidate_at->format('Y-m-d')).' '.$s->line_group_candidate_at->format('H:i') : '']
                    : null,
                'digestAt' => config('booking.line_digest_at'),
                'lastSentAt' => $s->line_digest_sent_at ? JaDate::dayLabel($s->line_digest_sent_at->format('Y-m-d')).' '.$s->line_digest_sent_at->format('H:i') : null,
            ],
            'notice' => session('line_notice'),
        ]);
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

    /** 候補のグループを、LINE 通知の送り先にする */
    public function chooseLineGroup(): RedirectResponse
    {
        $s = Setting::current();
        if (! $s->line_group_candidate_id) {
            throw ValidationException::withMessages(['line' => '候補のグループがありません。先に公式アカウントをお店の LINE グループに招待してください']);
        }
        $s->update(['line_group_id' => $s->line_group_candidate_id, 'line_group_candidate_id' => null, 'line_group_candidate_at' => null]);

        return back();
    }

    /** 送り先のグループにテストの1通を送る */
    public function testLine(LineMessenger $line): RedirectResponse
    {
        $s = Setting::current();
        if (! $s->line_group_id) {
            throw ValidationException::withMessages(['line' => '送り先のグループが決まっていません']);
        }
        try {
            $line->push($s->line_group_id, '【つちのね】テスト送信です。予約の通知はこのグループに届きます。', 'test-'.now()->timestamp);
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['line' => 'LINE に送れませんでした。トークンと、公式アカウントがグループにいるかを確かめてください']);
        }

        return back()->with('line_notice', 'テスト送信しました。グループに届いたか確かめてください');
    }

    /** グループの ID は一部だけ見せる（C1234…abcd） */
    private static function mask(?string $id): ?string
    {
        return $id ? mb_substr($id, 0, 5).'…'.mb_substr($id, -4) : null;
    }
}
