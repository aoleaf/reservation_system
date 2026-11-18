<?php

namespace Database\Seeders;

use App\Enums\Channel;
use App\Enums\ReservationKind;
use App\Enums\ReservationStatus;
use App\Enums\SlotStatus;
use App\Enums\UserRole;
use App\Models\Member;
use App\Models\MemberMonth;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\Mulberry32;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * 開発用のデータ。resources/js/mocks/seed.ts を1行ずつ移したもの。
 * 同じ乱数（mulberry32(20261120)）を同じ順で引くので、モックと同じ生徒・予約ができる。
 * そのため、乱数を引く順番（使わない値も含む）を変えないこと。
 */
class BookingDemoSeeder extends Seeder
{
    private const TIMES = [['10:00', '12:00'], ['13:00', '15:00'], ['15:30', '17:30']];

    private const CLOSED_DAYS = ['2026-11-04', '2026-11-10', '2026-11-17', '2026-11-26', '2026-12-02', '2026-12-08', '2026-12-16', '2026-12-24'];

    private const SURNAMES = ['佐藤', '鈴木', '高橋', '田中', '伊藤', '渡辺', '山本', '中村', '小林', '加藤', '吉田', '山田', '佐々木', '山口', '松本', '井上', '木村', '林', '斎藤', '清水'];

    private const GIVEN = ['陽子', '美咲', '恵子', '真由美', '由美', '直子', '智子', '裕子', '久美子', '麻衣', '健一', '誠', '大輔', '翔太', '美穂', '綾', '千尋', '和子', '花子', '結衣'];

    private const TRIAL_NAMES = ['青木 葵', '石川 遥', '前田 蓮', '藤田 紗希', '岡田 湊', '後藤 莉子', '長谷川 悠', '村上 七海', '近藤 樹', '石井 彩花', '坂本 陸', '遠藤 優奈'];

    private Mulberry32 $rand;

    /** @var array<string, array{key: string, date: string, start: string, end: string, capacity: int, trialOpen: bool, monthlyLimit: ?int, status: string}> */
    private array $slots = [];

    /** @var list<array<string, mixed>> */
    private array $reservations = [];

    public function run(): void
    {
        $this->rand = new Mulberry32(20261120);
        $settings = Setting::current();

        // 枠：11/1〜12/31 の全日に3枠
        foreach (['2026-11', '2026-12'] as $month) {
            for ($d = CarbonImmutable::parse("$month-01"); $d->format('Y-m') === $month; $d = $d->addDay()) {
                $date = $d->format('Y-m-d');
                foreach (self::TIMES as [$start, $end]) {
                    $key = $this->slotKey($date, $start);
                    $this->slots[$key] = [
                        'key' => $key, 'date' => $date, 'start' => $start, 'end' => $end, 'capacity' => 6,
                        'trialOpen' => $d->isWeekend(), 'monthlyLimit' => $d->isWeekend() ? $settings->weekend_monthly_limit : null,
                        'status' => in_array($date, self::CLOSED_DAYS, true) ? 'closed' : 'open',
                    ];
                }
            }
        }

        // 生徒 m01〜m40（電話番号で乱数を2つずつ引く）
        $members = [];
        for ($i = 0; $i < 40; $i++) {
            $id = sprintf('m%02d', $i + 1);
            $members[$id] = [
                'name' => self::SURNAMES[$i % 20].' '.self::GIVEN[($i * 3 + intdiv($i, 20) * 7) % 20],
                'phone' => $this->phone(),
                'email' => "$id@example.com",
            ];
        }

        // 月謝の固定配置（seed.ts と同じ）
        $fixed = [
            'm01' => [$this->slotKey('2026-11-05', '10:00'), $this->slotKey('2026-11-12', '13:00'), $this->slotKey('2026-11-25', '10:00'), $this->slotKey('2026-11-27', '15:30')],
            'm05' => [$this->slotKey('2026-11-21', '13:00')],
            'm06' => [$this->slotKey('2026-11-21', '13:00')],
            'm07' => [$this->slotKey('2026-11-21', '13:00')],
        ];
        $memberMonths = [];
        $order = array_merge(array_keys(array_intersect_key($members, $fixed)), array_keys(array_diff_key($members, $fixed)));
        foreach ($order as $mid) {
            $this->fillMonth($mid, '2026-11', $fixed[$mid] ?? [], '2026-10-20 10:00:00', $settings->sessions_per_month);
            $memberMonths[] = [$mid, '2026-11', '2026-10-20 10:00:00'];
        }
        // 12月：m02〜m26 の25名が提出済み。m01 と m27〜m40 は未提出
        foreach (array_keys($members) as $mid) {
            $n = (int) substr($mid, 1);
            $submitted = $n >= 2 && $n <= 26;
            if ($submitted) {
                $this->fillMonth($mid, '2026-12', [], '2026-11-05 10:00:00', $settings->sessions_per_month);
            }
            $memberMonths[] = [$mid, '2026-12', $submitted ? '2026-11-05 10:00:00' : null];
        }

        // 体験：月謝の後に入れる
        $this->addTrial($this->slotKey('2026-11-22', '10:00'), 2, Channel::Web, $settings->trial_price, ['token' => 'demo', 'name' => '森下 体験', 'phone' => '090-0000-0000', 'email' => 'demo@example.com']);
        $this->addTrial($this->slotKey('2026-11-21', '13:00'), 2, Channel::Web, $settings->trial_price);
        $this->addTrial($this->slotKey('2026-11-21', '13:00'), 1, Channel::Phone, $settings->trial_price);
        $this->addTrial($this->slotKey('2026-11-21', '10:00'), 1, Channel::Web, $settings->trial_price);
        $this->addTrial($this->slotKey('2026-11-21', '15:30'), 2, Channel::Dm, $settings->trial_price);
        $this->addTrial($this->slotKey('2026-11-22', '13:00'), 1, Channel::Phone, $settings->trial_price);
        $this->addTrial($this->slotKey('2026-11-22', '15:30'), 1, Channel::Web, $settings->trial_price);
        $channels = [Channel::Web, Channel::Web, Channel::Phone, Channel::Dm];
        foreach ($this->slots as $s) {
            if ($s['date'] < '2026-11-28' || ! $s['trialOpen'] || $s['status'] !== 'open') {
                continue;
            }
            if ($this->rand->next() < 0.3) {
                $n = 1 + (int) floor($this->rand->next() * 2);
                $this->addTrial($s['key'], $n, $this->pick($channels), $settings->trial_price);
            }
        }

        // 受付停止のデモ：予約が入ったまま、新しい予約だけ止めている枠
        $this->slots[$this->slotKey('2026-12-12', '10:00')]['status'] = 'paused';

        $this->save($members, $memberMonths);
    }

    private function slotKey(string $date, string $start): string
    {
        return $date.' '.$start;
    }

    private function phone(): string
    {
        $a = 1000 + (int) floor($this->rand->next() * 9000);
        $b = 1000 + (int) floor($this->rand->next() * 9000);

        return "090-$a-$b";
    }

    /** @template T @param list<T> $xs @return T */
    private function pick(array $xs): mixed
    {
        return $xs[(int) floor($this->rand->next() * count($xs))];
    }

    /** seed.ts の canBook と同じ（ここではすべて confirmed なので期限は見ない） */
    private function canBook(array $slot, string $kind, int $n): bool
    {
        if ($slot['status'] !== 'open' || $n < 1) {
            return false;
        }
        $used = 0;
        $usedMonthly = 0;
        foreach ($this->reservations as $r) {
            if ($r['slot'] === $slot['key']) {
                $used += $r['party_size'];
                $usedMonthly += $r['kind'] === ReservationKind::Monthly ? $r['party_size'] : 0;
            }
        }
        $free = $slot['capacity'] - $used;
        if ($kind === 'trial') {
            $rest = $slot['trialOpen'] ? max(0, $free) : 0;
        } else {
            $rest = $slot['monthlyLimit'] === null ? max(0, $free) : max(0, min($free, $slot['monthlyLimit'] - $usedMonthly));
        }

        return $rest >= $n;
    }

    private function addMonthly(string $memberId, string $key, string $createdAt): void
    {
        if (! $this->canBook($this->slots[$key], 'monthly', 1)) {
            throw new RuntimeException("seed: 月謝が入らない $key");
        }
        $this->reservations[] = ['slot' => $key, 'kind' => ReservationKind::Monthly, 'channel' => Channel::Member, 'party_size' => 1, 'amount' => 0, 'member' => $memberId, 'created_at' => $createdAt];
    }

    /** 月謝4回を、平日を中心に別々の日で入れる */
    private function fillMonth(string $memberId, string $month, array $preset, string $createdAt, int $sessions): void
    {
        $usedDates = [];
        foreach ($preset as $key) {
            $this->addMonthly($memberId, $key, $createdAt);
            $usedDates[$this->slots[$key]['date']] = true;
        }
        $open = array_values(array_filter($this->slots, fn ($s) => str_starts_with($s['date'], $month) && $s['status'] === 'open'));
        $weekdays = array_values(array_filter($open, fn ($s) => ! $s['trialOpen']));
        $weekends = array_values(array_filter($open, fn ($s) => $s['trialOpen']));
        $guard = 0;
        while (count($usedDates) < $sessions) {
            if (++$guard > 1000) {
                throw new RuntimeException("seed: $memberId の $month が埋まらない");
            }
            $slot = $this->rand->next() < 0.85 ? $this->pick($weekdays) : $this->pick($weekends);
            if (isset($usedDates[$slot['date']]) || ! $this->canBook($slot, 'monthly', 1)) {
                continue;
            }
            $this->addMonthly($memberId, $slot['key'], $createdAt);
            $usedDates[$slot['date']] = true;
        }
    }

    /** seed.ts の addTrial と同じ順で乱数を引く（名前 → 電話 → web ならトークン。上書きされる値も引く） */
    private function addTrial(string $key, int $n, Channel $channel, int $price, array $extra = []): void
    {
        if (! $this->canBook($this->slots[$key], 'trial', $n)) {
            return;
        }
        $name = $this->pick(self::TRIAL_NAMES);
        $phone = $this->phone();
        $seq = count($this->reservations) + 1;
        $token = $channel === Channel::Web ? 't'.$seq.'x'.(int) floor($this->rand->next() * 1e6) : null;
        $this->reservations[] = [
            'slot' => $key, 'kind' => ReservationKind::Trial, 'channel' => $channel, 'party_size' => $n, 'amount' => $price * $n,
            'name' => $extra['name'] ?? $name, 'phone' => $extra['phone'] ?? $phone,
            'email' => $extra['email'] ?? ($channel === Channel::Web ? 'guest@example.com' : null),
            'token' => $extra['token'] ?? $token, 'created_at' => '2026-11-10 12:00:00',
        ];
    }

    private function save(array $members, array $memberMonths): void
    {
        $slotIds = [];
        foreach ($this->slots as $s) {
            $slotIds[$s['key']] = Slot::query()->create([
                'date' => $s['date'], 'start_time' => $s['start'], 'end_time' => $s['end'], 'capacity' => $s['capacity'],
                'trial_open' => $s['trialOpen'], 'monthly_limit' => $s['monthlyLimit'], 'status' => SlotStatus::from($s['status']),
            ])->id;
        }
        $memberIds = [];
        foreach ($members as $key => $m) {
            $memberIds[$key] = Member::query()->create($m)->id;
        }
        foreach ($memberMonths as [$key, $month, $submittedAt]) {
            MemberMonth::query()->create(['member_id' => $memberIds[$key], 'month' => $month, 'submitted_at' => $submittedAt, 'change_used' => false]);
        }
        foreach ($this->reservations as $r) {
            $res = new Reservation([
                'code' => Reservation::newCode(CarbonImmutable::parse($r['created_at'])),
                'slot_id' => $slotIds[$r['slot']], 'kind' => $r['kind'], 'channel' => $r['channel'], 'party_size' => $r['party_size'],
                'status' => ReservationStatus::Confirmed, 'amount' => $r['amount'],
                'member_id' => isset($r['member']) ? $memberIds[$r['member']] : null,
                'contact_name' => $r['name'] ?? null, 'contact_phone' => $r['phone'] ?? null, 'contact_email' => $r['email'] ?? null,
                'cancel_token_hash' => isset($r['token']) ? Reservation::hashToken($r['token']) : null,
            ]);
            $res->created_at = $r['created_at'];
            $res->save();
        }

        // 開発用のスタッフ（パスワードは password）
        User::query()->firstOrCreate(['email' => 'staff@example.com'], ['name' => 'スタッフ', 'password' => 'password', 'role' => UserRole::Staff]);
    }
}
