<?php

namespace App\Support;

/**
 * メールアドレスと電話番号の形（resources/js/lib/contact.ts と同じ決まり）。
 * ・入力をそろえる（全角の英数字・ハイフンを半角に、前後の空白を取る、メールは小文字に）
 * ・形を確かめる（メールは「名前@ドメイン.xx」、電話は 0 で始まり、携帯などは 11 桁・固定電話などは 10 桁。ハイフン・括弧は可）
 */
final class ContactFormat
{
    /** ハイフンとして扱う文字（全角・長音・ダッシュなど） */
    private const DASHES = ['－', 'ー', '―', '‐', '−', '—', '–', 'ｰ'];

    public static function normalizeEmail(?string $v): string
    {
        return mb_strtolower(trim(mb_convert_kana((string) $v, 'as')));
    }

    public static function isEmail(string $v): bool
    {
        return mb_strlen($v) <= 255 && (bool) preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/u', $v);
    }

    public static function normalizePhone(?string $v): string
    {
        $v = mb_convert_kana(trim((string) $v), 'as');
        $v = str_replace(self::DASHES, '-', $v);
        $v = str_replace(['（', '）'], ['(', ')'], $v);

        return preg_replace('/\s+/u', '', $v);
    }

    public static function isPhone(string $v): bool
    {
        if (! preg_match('/^[0-9()\-]+$/', $v)) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $v);
        // 携帯（070・080・090）・IP 電話（050）・0800 は 11 桁、固定電話・0120・0570 などは 10 桁
        if (preg_match('/^0[5789]0/', $digits)) {
            return (bool) preg_match('/^0[5789]0\d{8}$/', $digits);
        }

        return (bool) preg_match('/^0[1-9]\d{8}$/', $digits);
    }

    public const EMAIL_MESSAGE = 'メールアドレスの形を確かめてください（例：taro@example.com）';

    public const PHONE_MESSAGE = '電話番号は 0 から始まる 10〜11 桁で入れてください（例：090-1234-5678）';
}
