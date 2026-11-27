<?php

namespace Tests\Feature\Import;

use App\Models\Member;
use App\Models\Reservation;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $content, bool $sjis = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $sjis ? mb_convert_encoding($content, 'SJIS-win', 'UTF-8') : $content);

        return $path;
    }

    public function test_生徒_Shift_JIS_の_CSV_を取り込み_同じメールは更新(): void
    {
        $f = $this->csv("名前,電話番号,メールアドレス\r\n佐藤 陽子,090-1111-2222,Yoko@Example.com\r\n鈴木 美咲,090-3333-4444,misaki@example.com\r\n", sjis: true);
        $this->artisan('import:members', ['file' => $f])->expectsOutputToContain('新しく 2 名、更新 0 名')->assertSuccessful();
        $this->assertSame('佐藤 陽子', Member::query()->where('email', 'yoko@example.com')->value('name'));

        $f2 = $this->csv("\xEF\xBB\xBFメールアドレス,名前,電話番号\nyoko@example.com,佐藤 ようこ,090-9999-9999\n"); // UTF-8 BOM・列の順番違い
        $this->artisan('import:members', ['file' => $f2])->expectsOutputToContain('新しく 0 名、更新 1 名')->assertSuccessful();
        $this->assertSame('佐藤 ようこ', Member::query()->where('email', 'yoko@example.com')->value('name'));
    }

    public function test_生徒_エラーが1行でもあれば何も入れない(): void
    {
        $f = $this->csv("名前,電話番号,メールアドレス\na,090-1111-1111,a@example.com\nb,090-2222-2222,not-an-email\nc,090-3333-3333,a@example.com\n");
        $this->artisan('import:members', ['file' => $f])->expectsOutputToContain('3行目')->expectsOutputToContain('4行目')->assertFailed();
        $this->assertSame(0, Member::query()->count());
    }

    public function test_生徒_見出しが無ければ止める_dry_run_は入れない(): void
    {
        $this->artisan('import:members', ['file' => $this->csv("名前,メール\n")])->expectsOutputToContain('見出しが足りません')->assertFailed();
        $this->artisan('import:members', ['file' => $this->csv("名前,電話番号,メールアドレス\na,090-1111-1111,a@example.com\n"), '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Member::query()->count());
    }

    public function test_生徒_会員番号で見分け_メールが無くても入れる(): void
    {
        $csv = "会員番号,名前,電話番号,メールアドレス\nT001,佐藤 陽子,090-1111-2222,student01@example.jp\nT002,鈴木 美咲,090-3333-4444,\n";
        $this->artisan('import:members', ['file' => $this->csv($csv, sjis: true)])->expectsOutputToContain('新しく 2 名、更新 0 名')->assertSuccessful();
        $this->assertNull(Member::query()->where('member_no', 'T002')->value('email'));

        // 同じ番号は上書き（名前・電話・メールも CSV のとおりに。空のメールは消す）
        $csv2 = "会員番号,名前,電話番号,メールアドレス\nｔ２,鈴木 みさき,090-5555-6666,misaki@example.jp\nT001,佐藤 陽子,090-1111-2222,\n";
        $this->artisan('import:members', ['file' => $this->csv($csv2)])->expectsOutputToContain('新しく 0 名、更新 2 名')->assertSuccessful();
        $m = Member::query()->where('member_no', 'T002')->first();
        $this->assertSame(['鈴木 みさき', '090-5555-6666', 'misaki@example.jp'], [$m->name, $m->phone, $m->email]);
        $this->assertNull(Member::query()->where('member_no', 'T001')->value('email'));
        $this->assertSame(2, Member::query()->count());
    }

    public function test_生徒_番号が空なら最大の次を振る_CSV_の番号とは重ねない(): void
    {
        Member::factory()->create(['member_no' => 'T003', 'active' => false]);
        // 番号の無い行が先にあっても、CSV の T010 のあとに振る
        $csv = "会員番号,名前,電話番号,メールアドレス\n,新しい 人,090-7777-8888,\nT010,佐藤 陽子,090-1111-2222,\n,もう1人,090-9999-0000,\n";
        $this->artisan('import:members', ['file' => $this->csv($csv)])
            ->expectsOutputToContain('新しく 3 名、更新 0 名')
            ->expectsOutputToContain('T011 新しい 人、T012 もう1人')
            ->assertSuccessful();
        $this->assertSame('T010', Member::query()->where('name', '佐藤 陽子')->value('member_no'));

        // 番号もメールも無い行をもう一度流すと、同じ人が2人できないように止める
        $this->artisan('import:members', ['file' => $this->csv("名前,電話番号\n新しい 人,090-7777-8888\n")])
            ->expectsOutputToContain('T011 でもう登録されています')->assertFailed();
        $this->assertSame(4, Member::query()->count());
    }

    public function test_生徒_会員番号の形_重なり_メールの持ち主違いはエラー(): void
    {
        Member::factory()->create(['member_no' => 'T001', 'email' => 'yoko@example.jp']);
        $csv = "会員番号,名前,電話番号,メールアドレス\nS001,a,090-1111-1111,\nT005,b,090-2222-2222,\nT5,c,090-3333-3333,\nT006,d,090-4444-4444,yoko@example.jp\n,e,090-5555-5555,\n,e,090-5555-5555,\n";
        $this->artisan('import:members', ['file' => $this->csv($csv)])
            ->expectsOutputToContain('2行目：会員番号「S001」の形が違います')
            ->expectsOutputToContain('4行目：会員番号 T005 が 3行目と同じです')
            ->expectsOutputToContain('5行目：メールアドレス yoko@example.jp は T001 の生徒さんが使っています')
            ->expectsOutputToContain('7行目：e さん（090-5555-5555）は 6行目と同じです')
            ->assertFailed();
        $this->assertSame(1, Member::query()->count());
    }

    public function test_月謝の予約_4回で提出済み_同じ予約は飛ばす(): void
    {
        $this->artisan('slots:generate', ['month' => '2026-12']);
        $m = Member::factory()->create(['email' => 'yoko@example.com']);
        $csv = "メールアドレス,日付,開始時刻\nyoko@example.com,2026/12/1,10:00\nyoko@example.com,2026-12-03,13:00\nyoko@example.com,2026-12-04,15:30\nyoko@example.com,2026-12-07,10:00\n";
        $this->artisan('import:monthly', ['file' => $this->csv($csv, sjis: true)])->expectsOutputToContain('入れた 4 件、もう入っていた 0 件、提出済みにした 1 件')->assertSuccessful();
        $this->assertNotNull($m->monthOf('2026-12')->submitted_at);
        $this->artisan('import:monthly', ['file' => $this->csv($csv)])->expectsOutputToContain('入れた 0 件、もう入っていた 4 件')->assertSuccessful();
        $this->assertSame(4, Reservation::query()->count());
    }

    public function test_月謝の予約_満席_同じ日_いない生徒_無い枠はエラーで何も入れない(): void
    {
        $this->artisan('slots:generate', ['month' => '2026-12']);
        $sat = Slot::query()->where('date', '2026-12-05')->where('start_time', '10:00')->first();
        Reservation::factory()->count(3)->for($sat)->monthly()->create(); // 土曜の月謝は3名まで
        Member::factory()->create(['email' => 'yoko@example.com']);
        $csv = "メールアドレス,日付,開始時刻\nyoko@example.com,2026-12-01,10:00\nyoko@example.com,2026-12-05,10:00\nyoko@example.com,2026-12-01,13:00\nnobody@example.com,2026-12-02,10:00\nyoko@example.com,2026-12-40,10:00\nyoko@example.com,2026-12-02,11:00\n";
        $this->artisan('import:monthly', ['file' => $this->csv($csv)])
            ->expectsOutputToContain('3行目：2026-12-05 10:00 は満席です')
            ->expectsOutputToContain('4行目')
            ->expectsOutputToContain('5行目')
            ->expectsOutputToContain('6行目')
            ->expectsOutputToContain('7行目')
            ->expectsOutputToContain('何も取り込んでいません')
            ->assertFailed();
        $this->assertSame(3, Reservation::query()->count());
    }
}
