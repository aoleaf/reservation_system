<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Line\LineMessenger;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * LINE からの通知（webhook）。署名を確かめてから読む。CSRF の対象外（bootstrap/app.php）。
 * ・公式アカウントがグループに招待された（join）→ そのグループを「候補」にする（送り先にするのはスタッフが設定画面で決める）
 * ・送り先のグループから外された（leave）→ 送り先から外す
 */
class LineWebhookController extends Controller
{
    public function __invoke(Request $request, LineMessenger $line): Response
    {
        if (! $line->verifySignature($request->getContent(), $request->header('X-Line-Signature'))) {
            Log::warning('LINE の webhook の署名が合いません');

            return response('invalid', 400);
        }

        $s = Setting::current();
        foreach ($request->input('events', []) as $event) {
            $source = $event['source'] ?? [];
            if (($source['type'] ?? null) !== 'group' || ! isset($source['groupId'])) {
                continue;
            }
            if (($event['type'] ?? null) === 'join') {
                $s->update(['line_group_candidate_id' => $source['groupId'], 'line_group_candidate_at' => CarbonImmutable::now()]);
            } elseif (($event['type'] ?? null) === 'leave') {
                // 外されたグループが送り先・候補なら外す（ほかのグループのことなら何もしない）
                if ($s->line_group_id === $source['groupId']) {
                    $s->line_group_id = null;
                }
                if ($s->line_group_candidate_id === $source['groupId']) {
                    $s->line_group_candidate_id = null;
                }
                $s->save();
            }
        }

        return response('ok');
    }
}
