import MobileShell from '@/components/mobile-shell';
import { requestMemberLogin } from '@/lib/actions';
import type { MemberLoginProps } from '@/types/pages';
import { useState } from 'react';

const input = 'border-line w-full rounded-md border bg-white px-3 py-2.5 text-base focus:border-brand focus:outline-none';

/** 生徒さんのログイン：メールアドレスにログイン用のリンクを送る（パスワードなし） */
export default function MemberLogin({ sent, email: sentTo, minutes, errors: pageErrors }: MemberLoginProps) {
    const [email, setEmail] = useState(sentTo);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const messages = Object.values({ ...(pageErrors ?? {}), ...errors });

    const send = () => {
        setBusy(true);
        setErrors({});
        requestMemberLogin({ email }, { onError: setErrors, onFinish: () => setBusy(false) });
    };

    return (
        <MobileShell title="生徒さんのログイン">
            {sent ? (
                <div className="bg-brand-faint mb-5 rounded-lg p-4 text-sm leading-relaxed">
                    <p className="font-bold">ログイン用のリンクを送りました</p>
                    <p className="mt-1">
                        <span className="break-all">{sentTo}</span> が登録されているメールアドレスなら、まもなくメールが届きます。{minutes}
                        分以内に、メールのリンクを開いてください。
                    </p>
                    <p className="text-ink-soft mt-2 text-xs">届かないときは、迷惑メールのフォルダーもご確認ください。</p>
                </div>
            ) : (
                <p className="mb-5 text-sm leading-relaxed">
                    登録しているメールアドレスを入れてください。ログイン用のリンクをお送りします（パスワードは要りません）。
                </p>
            )}

            <label className="block">
                <span className="mb-1 block text-sm font-bold">メールアドレス</span>
                <input className={input} type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} />
            </label>
            {messages.map((m) => (
                <p key={m} role="alert" className="text-alert mt-2 text-sm font-bold">
                    {m}
                </p>
            ))}
            <button
                type="button"
                onClick={send}
                disabled={busy || email.trim() === ''}
                className="bg-brand disabled:bg-line disabled:text-ink-soft mt-4 block w-full rounded-lg py-3 font-bold text-white"
            >
                {sent ? 'もう一度リンクを送る' : 'ログイン用のリンクを送る'}
            </button>
        </MobileShell>
    );
}
