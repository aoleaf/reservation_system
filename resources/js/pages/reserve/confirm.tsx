import MobileShell from '@/components/mobile-shell';
import NotFound from '@/components/not-found';
import { createWebReservation } from '@/lib/actions';
import { yen } from '@/lib/format';
import type { ReserveConfirmProps } from '@/types/pages';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

/** 内容・金額・キャンセル規定の確認。「支払う」で予約を作る */
export default function ReserveConfirm({ slot, slotLabel, partySize, contact, unitPrice, amount, cancelPolicy }: ReserveConfirmProps) {
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    if (!slot) {
        return (
            <MobileShell title="予約内容の確認" back={route('reserve.index')}>
                <NotFound message="枠が見つかりません" backHref={route('reserve.index')} backLabel="枠の一覧へ戻る" />
            </MobileShell>
        );
    }

    const back = route('reserve.form', { slot: slot.id, n: partySize, name: contact.name, phone: contact.phone, email: contact.email ?? '' });
    const pay = () => {
        setBusy(true);
        setErrors({});
        createWebReservation({ slotId: slot.id, partySize, contact }, { onError: setErrors, onFinish: () => setBusy(false) });
    };
    const errorList = Object.values(errors);

    return (
        <MobileShell title="予約内容の確認" back={back}>
            <dl className="border-line divide-line divide-y rounded-lg border bg-white text-sm">
                {[
                    ['日時', slotLabel],
                    ['人数', `${partySize}名`],
                    ['お名前', contact.name],
                    ['電話番号', contact.phone],
                    ['メール', contact.email || '—'],
                    ['単価', yen(unitPrice)],
                ].map(([k, v]) => (
                    <div key={k} className="flex gap-3 px-3 py-2.5">
                        <dt className="text-ink-soft w-20 shrink-0">{k}</dt>
                        <dd className="min-w-0 break-all">{v}</dd>
                    </div>
                ))}
                <div className="flex items-baseline gap-3 px-3 py-3">
                    <dt className="text-ink-soft w-20 shrink-0">お支払い</dt>
                    <dd className="text-brand text-xl font-bold">{yen(amount)}</dd>
                </div>
            </dl>

            <section className="mt-5">
                <h2 className="mb-2 text-sm font-bold">キャンセル規定</h2>
                <ul className="text-ink-soft list-disc space-y-1 pl-5 text-sm">
                    {cancelPolicy.map((p) => (
                        <li key={p}>{p}</li>
                    ))}
                </ul>
            </section>

            {errorList.length > 0 && (
                <div role="alert" className="border-alert/40 bg-alert-soft text-alert mt-5 rounded-lg border p-3 text-sm font-bold">
                    {errorList.map((e) => (
                        <p key={e}>{e}</p>
                    ))}
                    <Link href={route('reserve.index')} className="mt-2 inline-block underline">
                        枠の一覧へ戻る
                    </Link>
                </div>
            )}

            <button type="button" onClick={pay} disabled={busy} className="bg-brand mt-6 block w-full rounded-lg py-3 font-bold text-white disabled:opacity-60">
                {yen(amount)} を支払う
            </button>
            <p className="text-ink-soft mt-2 text-center text-xs">確認用の画面のため、実際の支払いは行われません</p>
            <Link href={back} className="text-brand mt-4 block text-center text-sm underline">
                入力内容を直す
            </Link>
        </MobileShell>
    );
}
