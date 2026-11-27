// メールアドレスと電話番号の形（app/Support/ContactFormat.php と同じ決まり）。
// 画面では入力中の案内に使う。最終的な確認はサーバーで行う。

export const EMAIL_MESSAGE = 'メールアドレスの形を確かめてください（例：taro@example.com）';
export const PHONE_MESSAGE = '電話番号の桁数を確かめてください（携帯は 11 桁・固定電話は 10 桁。例：090-1234-5678）';

const DASHES = /[－ー―‐−—–ｰ]/g;
/** 全角スペース（そのまま書くと見えないので文字コードで） */
const FULL_WIDTH_SPACE = String.fromCharCode(0x3000);

/** 全角の英数字・記号を半角に（全角スペースは半角スペースに） */
const toHalfWidth = (v: string) => v.replace(/[！-～]/g, (c) => String.fromCharCode(c.charCodeAt(0) - 0xfee0)).replaceAll(FULL_WIDTH_SPACE, ' ');

export const normalizeEmail = (v: string) => toHalfWidth(v).trim().toLowerCase();

export const isEmail = (v: string) => v.length <= 255 && /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/u.test(v);

export const normalizePhone = (v: string) => toHalfWidth(v.trim()).replace(DASHES, '-').replace(/\s+/g, '');

/** 0 で始まり、携帯（070・080・090）・IP 電話（050）・0800 は 11 桁、固定電話・0120・0570 などは 10 桁。ハイフン・括弧は可 */
export function isPhone(v: string): boolean {
    if (!/^[0-9()-]+$/.test(v)) return false;
    const digits = v.replace(/\D/g, '');
    if (/^0[5789]0/.test(digits)) return /^0[5789]0\d{8}$/.test(digits);
    return /^0[1-9]\d{8}$/.test(digits);
}
