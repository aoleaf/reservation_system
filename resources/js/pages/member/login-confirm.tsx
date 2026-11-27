import MobileShell from '@/components/mobile-shell';
import { confirmMemberLogin } from '@/lib/actions';
import type { MemberLoginConfirmProps } from '@/types/pages';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

/**
 * ログイン用リンクを開いた画面。ボタンを押したときにだけログインする
 * （メールのウイルスチェックがリンクを先に開いても、リンクが使われないように）
 */
export default function MemberLoginConfirm({ token, valid }: MemberLoginConfirmProps) {
    const [busy, setBusy] = useState(false);

    return (
        <MobileShell title="生徒さんのログイン">
            {valid ? (
                <>
                    <p className="mb-5 text-sm">下のボタンを押すと、マイページにログインします。</p>
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => {
                            setBusy(true);
                            confirmMemberLogin({ token }, { onFinish: () => setBusy(false) });
                        }}
                        className="bg-brand block w-full rounded-lg py-3 font-bold text-white disabled:opacity-60"
                    >
                        ログインする
                    </button>
                </>
            ) : (
                <div className="border-line rounded-lg border bg-white p-5 text-center text-sm">
                    <p className="mb-3">このリンクは使えません（期限切れ、または使用済みです）。</p>
                    <Link href={route('member.login')} className="text-brand underline">
                        もう一度リンクを送る
                    </Link>
                </div>
            )}
        </MobileShell>
    );
}
