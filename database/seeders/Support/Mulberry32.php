<?php

namespace Database\Seeders\Support;

/**
 * resources/js/mocks/seed.ts の mulberry32 と同じ乱数（同じ seed から同じ列を出す）。
 * JS の 32bit 整数の計算（Math.imul、>>>）を PHP の 64bit 整数で再現する。
 */
final class Mulberry32
{
    private int $a;

    public function __construct(int $seed)
    {
        $this->a = self::i32($seed);
    }

    /** 0 以上 1 未満 */
    public function next(): float
    {
        $this->a = self::i32($this->a + 0x6D2B79F5);
        $a = $this->a;
        $t = self::imul($a ^ self::urs($a, 15), 1 | $a);
        $t = self::i32($t + self::imul($t ^ self::urs($t, 7), 61 | $t)) ^ $t;

        return (self::urs($t ^ self::urs($t, 14), 0)) / 4294967296;
    }

    /** 符号付き 32bit に丸める（JS の |0） */
    private static function i32(int $x): int
    {
        $x &= 0xFFFFFFFF;

        return $x >= 0x80000000 ? $x - 0x100000000 : $x;
    }

    /** 符号なし右シフト（JS の >>>） */
    private static function urs(int $x, int $n): int
    {
        return ($x & 0xFFFFFFFF) >> $n;
    }

    /** JS の Math.imul（32bit の掛け算。64bit であふれないよう16bit ずつ） */
    private static function imul(int $a, int $b): int
    {
        $a &= 0xFFFFFFFF;
        $b &= 0xFFFFFFFF;
        $lo = ($a & 0xFFFF) * $b;
        $hi = (($a >> 16) * $b) & 0xFFFF;

        return self::i32($lo + ($hi << 16));
    }
}
