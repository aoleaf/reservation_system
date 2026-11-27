<?php

namespace App\Support;

use App\Models\Member;

/**
 * 生徒さんの会員番号（T001 の形）。
 * ・形は「T ＋ 数字」。3桁に満たないときは 0 で埋める（T043）。T999 の次は T1000
 * ・次の番号は、今ある番号の最大 ＋ 1。欠番は埋めない（ほかの人が使っていた番号を使い回さないため）
 */
final class MemberNumber
{
    /** 入力をそろえる（全角を半角に、t を T に、桁を 3 桁以上にそろえる）。形が違うときは null */
    public static function normalize(?string $v): ?string
    {
        $v = strtoupper(trim(mb_convert_kana((string) $v, 'as')));
        if (! preg_match('/^T(\d+)$/', $v, $m) || (int) $m[1] < 1) {
            return null;
        }

        return self::format((int) $m[1]);
    }

    public static function format(int $n): string
    {
        return sprintf('T%03d', $n);
    }

    /** 番号の数字の部分（T043 → 43） */
    public static function value(string $number): int
    {
        return (int) substr($number, 1);
    }

    /** 次に振る番号（今ある番号の最大 ＋ 1。退会した生徒さんの番号も数える） */
    public static function next(): string
    {
        $max = Member::query()->pluck('member_no')->filter()->map(fn (string $n) => self::value($n))->max() ?? 0;

        return self::format($max + 1);
    }
}
