<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Support\ContactFormat;
use App\Support\CsvFile;
use App\Support\MemberNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 生徒さんの一覧を CSV から取り込む。見出し：会員番号, 名前, 電話番号, メールアドレス（会員番号とメールアドレスの列・値は無くてもよい）
 * ・会員番号があれば、その番号の生徒さんを上書きする（いなければ、その番号で新しく作る）
 * ・会員番号が無ければ、メールアドレスで探して上書きする。どちらも無ければ新しく作り、会員番号を自動で振る
 * 1行でもエラーがあれば何も入れない。
 */
class ImportMembers extends Command
{
    protected $signature = 'import:members {file : CSV のパス} {--dry-run : 入れずに結果だけ見る}';

    protected $description = '生徒さんの一覧を CSV から取り込む（会員番号, 名前, 電話番号, メールアドレス）';

    public function handle(): int
    {
        try {
            $rows = CsvFile::read($this->argument('file'), ['名前', '電話番号']);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $hasEmailColumn = $rows !== [] && array_key_exists('メールアドレス', $rows[0]['row']);

        $errors = [];
        $plans = []; // 入れるもの：[member, number, name, phone, email]
        $seenNumber = $seenEmail = $seenNamePhone = [];
        foreach ($rows as ['line' => $line, 'row' => $r]) {
            // 全角を半角に、メールは小文字にそろえてから確かめる
            $rawNumber = $r['会員番号'] ?? '';
            $number = $rawNumber === '' ? null : MemberNumber::normalize($rawNumber);
            $email = ContactFormat::normalizeEmail($r['メールアドレス'] ?? '');
            $email = $email === '' ? null : $email;
            $phone = ContactFormat::normalizePhone($r['電話番号']);
            $name = $r['名前'];
            $rowErrors = [];

            if ($rawNumber !== '' && $number === null) {
                $rowErrors[] = "{$line}行目：会員番号「{$rawNumber}」の形が違います（T001 の形）";
            } elseif ($number !== null && isset($seenNumber[$number])) {
                $rowErrors[] = "{$line}行目：会員番号 {$number} が {$seenNumber[$number]}行目と同じです";
            }
            if ($name === '' || mb_strlen($name) > 100) {
                $rowErrors[] = "{$line}行目：名前を確かめてください";
            }
            if (! ContactFormat::isPhone($phone)) {
                $hint = preg_match('/^[1-9]\d{8,9}$/', preg_replace('/\D/', '', $phone)) ? '（Excel で先頭の 0 が消えていないか確かめてください）' : '';
                $rowErrors[] = "{$line}行目：電話番号「{$r['電話番号']}」の形が違います{$hint}";
            }
            if ($email !== null && ! ContactFormat::isEmail($email)) {
                $rowErrors[] = "{$line}行目：メールアドレス「{$r['メールアドレス']}」の形が違います";
            } elseif ($email !== null && isset($seenEmail[$email])) {
                $rowErrors[] = "{$line}行目：メールアドレス {$email} が {$seenEmail[$email]}行目と同じです";
            }
            if ($number !== null) {
                $seenNumber[$number] ??= $line;
            }
            if ($email !== null) {
                $seenEmail[$email] ??= $line;
            }
            if ($rowErrors) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            // 上書きする生徒さんを探す（会員番号 → メールアドレスの順）
            $member = match (true) {
                $number !== null => Member::query()->where('member_no', $number)->first(),
                $email !== null => Member::query()->where('email', $email)->first(),
                default => null,
            };
            if ($number === null && $email === null) {
                // 見分けるものが無い行は新しく作る。同じ CSV を2回流して同じ人が2人できないように、名前と電話番号が同じ人がいれば止める
                $twin = Member::query()->where('name', $name)->where('phone', $phone)->value('member_no');
                $key = $name."\n".$phone;
                if ($twin || isset($seenNamePhone[$key])) {
                    $errors[] = "{$line}行目：{$name} さん（{$phone}）は".($twin ? " {$twin} でもう登録されています" : " {$seenNamePhone[$key]}行目と同じです").'。会員番号を書いてください';

                    continue;
                }
                $seenNamePhone[$key] = $line;
            }
            if ($email !== null) {
                $other = Member::query()->where('email', $email)->when($member, fn ($q) => $q->whereKeyNot($member->id))->value('member_no');
                if ($other) {
                    $errors[] = "{$line}行目：メールアドレス {$email} は {$other} の生徒さんが使っています";

                    continue;
                }
            }
            $plans[] = compact('member', 'number', 'name', 'phone', 'email');
        }
        if ($errors) {
            array_map(fn ($e) => $this->error($e), $errors);
            $this->error('エラーがあるので、何も取り込んでいません');

            return self::FAILURE;
        }

        // 番号の決まっている行を先に入れ、自動で振る行はあとにする（CSV に書いた番号と重ならないように）
        usort($plans, fn ($a, $b) => ($a['number'] === null && ! $a['member']) <=> ($b['number'] === null && ! $b['member']));

        $created = $updated = 0;
        $assigned = [];
        DB::beginTransaction();
        foreach ($plans as $p) {
            $m = $p['member'] ?? new Member(['member_no' => $p['number']]);
            $m->exists ? $updated++ : $created++;
            $m->fill(['name' => $p['name'], 'phone' => $p['phone'], 'active' => true]);
            if ($hasEmailColumn) {
                $m->email = $p['email']; // 列があって空なら、メールを消す（CSV のとおりにする）
            }
            $auto = ! $m->exists && $m->member_no === null;
            $m->save();
            if ($auto) {
                $assigned[] = "{$m->member_no} {$m->name}";
            }
        }
        $this->option('dry-run') ? DB::rollBack() : DB::commit();

        $this->info(($this->option('dry-run') ? '（確認だけ・入れていません）' : '')."新しく {$created} 名、更新 {$updated} 名");
        if ($assigned) {
            $this->info('会員番号を振りました（次から CSV に書いてください）：'.implode('、', $assigned));
        }

        return self::SUCCESS;
    }
}
