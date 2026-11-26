<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Support\CsvFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * 生徒さんの一覧を CSV から取り込む。見出し：名前, 電話番号, メールアドレス
 * 同じメールアドレスの生徒さんがいれば、名前と電話番号を更新する。1行でもエラーがあれば何も入れない。
 */
class ImportMembers extends Command
{
    protected $signature = 'import:members {file : CSV のパス} {--dry-run : 入れずに結果だけ見る}';

    protected $description = '生徒さんの一覧を CSV から取り込む（名前, 電話番号, メールアドレス）';

    public function handle(): int
    {
        try {
            $rows = CsvFile::read($this->argument('file'), ['名前', '電話番号', 'メールアドレス']);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $errors = [];
        $seen = [];
        foreach ($rows as ['line' => $line, 'row' => $r]) {
            $email = mb_strtolower($r['メールアドレス']);
            $v = Validator::make(['name' => $r['名前'], 'phone' => $r['電話番号'], 'email' => $email], [
                'name' => ['required', 'max:100'], 'phone' => ['required', 'max:32'], 'email' => ['required', 'email', 'max:255'],
            ]);
            if ($v->fails()) {
                $errors[] = "{$line}行目：名前・電話番号・メールアドレスを確かめてください";
            } elseif (isset($seen[$email])) {
                $errors[] = "{$line}行目：メールアドレス {$email} が {$seen[$email]}行目と同じです";
            }
            $seen[$email] ??= $line;
        }
        if ($errors) {
            array_map(fn ($e) => $this->error($e), $errors);
            $this->error('エラーがあるので、何も取り込んでいません');

            return self::FAILURE;
        }

        $created = $updated = 0;
        DB::beginTransaction();
        foreach ($rows as ['row' => $r]) {
            $m = Member::query()->firstOrNew(['email' => mb_strtolower($r['メールアドレス'])]);
            $m->exists ? $updated++ : $created++;
            $m->fill(['name' => $r['名前'], 'phone' => $r['電話番号'], 'active' => true])->save();
        }
        $this->option('dry-run') ? DB::rollBack() : DB::commit();

        $this->info(($this->option('dry-run') ? '（確認だけ・入れていません）' : '')."新しく {$created} 名、更新 {$updated} 名");

        return self::SUCCESS;
    }
}
