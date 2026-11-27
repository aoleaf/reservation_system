<?php

namespace Tests\Unit;

use App\Support\ContactFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** メールアドレスと電話番号の形（resources/js/lib/contact.ts と同じ決まり） */
class ContactFormatTest extends TestCase
{
    public static function emails(): array
    {
        return [
            ['taro@example.com', true], ['taro.yamada+test@mail.example.co.jp', true],
            ['abc', false], ['taro@', false], ['@example.com', false], ['taro@example', false], ['taro@@example.com', false], ['taro @example.com', false], ['taro@example.c', false],
        ];
    }

    #[DataProvider('emails')]
    public function test_メールアドレスの形(string $v, bool $ok): void
    {
        $this->assertSame($ok, ContactFormat::isEmail(ContactFormat::normalizeEmail($v)));
    }

    public function test_メールは全角を半角に_小文字に_空白を取る(): void
    {
        $this->assertSame('taro@example.com', ContactFormat::normalizeEmail(' Ｔａｒｏ＠Ｅｘａｍｐｌｅ．ｃｏｍ '));
    }

    public static function phones(): array
    {
        return [
            ['090-1234-5678', true], ['09012345678', true], ['03-1234-5678', true], ['0312345678', true], ['(03)1234-5678', true], ['0120-123-456', true], ['0570-123-456', true], ['050-1234-5678', true], ['0800-123-4567', true],
            ['9012345678', false], // Excel で先頭の 0 が消えた形
            ['090-1234-567', false], // 携帯は 11 桁
            ['03-1234-56789', false], // 固定電話は 10 桁
 ['090-1234-56789', false], ['abc', false], ['+81-90-1234-5678', false], ['090-1234-5678 内線1', false], ['', false],
        ];
    }

    #[DataProvider('phones')]
    public function test_電話番号の形(string $v, bool $ok): void
    {
        $this->assertSame($ok, ContactFormat::isPhone(ContactFormat::normalizePhone($v)));
    }

    public function test_電話は全角の数字とハイフンを半角に(): void
    {
        $this->assertSame('090-1234-5678', ContactFormat::normalizePhone('０９０－１２３４ー５６７８'));
        $this->assertSame('090-1234-5678', ContactFormat::normalizePhone(' 090 - 1234 - 5678 '));
    }
}
