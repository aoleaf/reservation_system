<?php

namespace Tests\Feature\Line;

use App\Enums\Channel;
use App\Enums\SlotStatus;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\Slot;
use App\Services\Line\LineMessenger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 送った中身を覚えておく、テスト用の LINE */
class FakeLine implements LineMessenger
{
    /** @var list<array{to: string, text: string, key: string}> */
    public array $sent = [];

    public function push(string $to, string $text, string $retryKey): void
    {
        $this->sent[] = ['to' => $to, 'text' => $text, 'key' => $retryKey];
    }

    public function verifySignature(string $body, ?string $signature): bool
    {
        return $signature === 'ok';
    }
}

class DailyDigestTest extends TestCase
{
    use RefreshDatabase;

    private FakeLine $line;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-28 08:00');
        $this->line = new FakeLine;
        $this->app->instance(LineMessenger::class, $this->line);
        Setting::current()->update(['line_group_id' => 'Cshop']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_朝のまとめに枠ごとの予約が入り_電話番号は載せない(): void
    {
        $s10 = Slot::factory()->weekend('2026-11-28')->create(['start_time' => '10:00', 'end_time' => '12:00']);
        Slot::factory()->weekend('2026-11-28')->create(['start_time' => '13:00', 'end_time' => '15:00']);
        Slot::factory()->weekend('2026-11-28')->status(SlotStatus::Paused)->create(['start_time' => '15:30', 'end_time' => '17:30']);
        Reservation::factory()->for($s10)->monthly(Member::factory()->create(['name' => '佐藤 陽子']))->create();
        Reservation::factory()->for($s10)->create(['contact_name' => '青木 葵', 'party_size' => 2, 'contact_phone' => '090-9999-9999']);
        Reservation::factory()->for($s10)->create(['contact_name' => '前田 蓮', 'channel' => Channel::Phone]);

        $this->artisan('line:daily-digest')->expectsOutputToContain('送りました')->assertSuccessful();
        $this->assertCount(1, $this->line->sent);
        $text = $this->line->sent[0]['text'];
        $this->assertSame('Cshop', $this->line->sent[0]['to']);
        $this->assertStringContainsString('【つちのね】11月28日（土）の予約', $text);
        $this->assertStringContainsString("■ 10:00〜12:00（月謝1・体験3／6）\n・月謝 佐藤 陽子\n・体験 青木 葵 2名（支払済・Web）\n・体験 前田 蓮 1名（当日払い・電話）", $text);
        $this->assertStringContainsString("■ 13:00〜15:00（月謝0・体験0／6）\n予約なし", $text);
        $this->assertStringContainsString('■ 15:30〜17:30（月謝0・体験0／6・受付停止）', $text);
        $this->assertStringNotContainsString('090-9999-9999', $text);
    }

    public function test_同じ日に2回は送らない_force_で送り直す(): void
    {
        Slot::factory()->weekend('2026-11-28')->create();
        $this->artisan('line:daily-digest');
        $this->artisan('line:daily-digest')->expectsOutputToContain('送りませんでした');
        $this->assertCount(1, $this->line->sent);
        $this->artisan('line:daily-digest', ['--force' => true]);
        $this->assertCount(2, $this->line->sent);
        $this->assertNotSame($this->line->sent[0]['key'], $this->line->sent[1]['key']);
    }

    public function test_お休みの日_送り先が未設定なら送らない(): void
    {
        Slot::factory()->weekend('2026-11-28')->status(SlotStatus::Closed)->create();
        $this->artisan('line:daily-digest');
        $this->assertStringContainsString('本日はお休みです', $this->line->sent[0]['text']);

        Setting::current()->update(['line_group_id' => null, 'line_digest_sent_at' => null]);
        $this->artisan('line:daily-digest')->expectsOutputToContain('送りませんでした');
        $this->assertCount(1, $this->line->sent);
    }

    public function test_毎朝8時に日本時間で動く(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('0 8 * * *  php artisan line:daily-digest');
    }
}
