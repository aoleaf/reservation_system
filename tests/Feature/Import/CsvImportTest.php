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
