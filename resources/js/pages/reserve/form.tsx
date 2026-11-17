import MobileShell from '@/components/mobile-shell';
import NotFound from '@/components/not-found';
import { yen } from '@/lib/format';
import type { ReserveFormProps } from '@/types/pages';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

const input = 'border-line w-full rounded-md border bg-white px-3 py-2.5 text-base focus:border-brand focus:outline-none';

/** 人数と連絡先の入力。入力途中の値はストアに置かず、クエリーで確認画面へ渡す */
export default function ReserveForm({ slot, slotLabel, unitPrice, options, initial }: ReserveFormProps) {
    const [n, setN] = useState(initial.partySize);
    const [name, setName] = useState(initial.contact.name);
    const [phone, setPhone] = useState(initial.contact.phone);
    const [email, setEmail] = useState(initial.contact.email ?? '');

    if (!slot) {
        return (
            <MobileShell title="予約内容の入力" back={route('reserve.index')}>
                <NotFound message="枠が見つかりません" backHref={route('reserve.index')} backLabel="枠の一覧へ戻る" />
            </MobileShell>
        );
    }

    const amount = options.find((o) => o.n === n)?.amount;
    const ready = slot.bookable && name.trim() !== '' && phone.trim() !== '' && email.trim() !== '';

    return (
        <MobileShell title="予約内容の入力" back={route('reserve.index')}>
            <section className="border-line mb-5 rounded-lg border bg-white p-3">
                <p className="font-bold">{slotLabel}</p>
                {slot.bookable && <p className="text-brand mt-1 text-sm">残り{slot.remaining}席</p>}
            </section>

            {!slot.bookable ? (
                <NotFound message="この枠は満席です。別の枠をお選びください。" backHref={route('reserve.index')} backLabel="枠の一覧へ戻る" />
            ) : (
                <div className="space-y-4">
                    <label className="block">
                        <span className="mb-1 block text-sm font-bold">人数</span>
                        <select className={input} value={n} onChange={(e) => setN(Number(e.target.value))}>
                            {options.map((o) => (
                                <option key={o.n} value={o.n}>
                                    {o.n}名
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-sm font-bold">お名前（必須）</span>
                        <input className={input} value={name} onChange={(e) => setName(e.target.value)} autoComplete="name" />
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-sm font-bold">電話番号（必須）</span>
                        <input className={input} value={phone} onChange={(e) => setPhone(e.target.value)} type="tel" autoComplete="tel" />
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-sm font-bold">メールアドレス（必須）</span>
                        <input className={input} value={email} onChange={(e) => setEmail(e.target.value)} type="email" autoComplete="email" />
                    </label>

                    <div className="bg-brand-faint rounded-lg p-3">
                        <div className="flex items-baseline justify-between">
                            <span className="text-sm">お支払い金額</span>
                            <span className="text-brand text-xl font-bold">{amount !== undefined ? yen(amount) : '—'}</span>
                        </div>
                        <p className="text-ink-soft mt-1 text-right text-sm tabular-nums">
                            {yen(unitPrice)} × {n}名
                        </p>
                    </div>

                    {ready ? (
                        <Link
                            href={route('reserve.confirm', { slot: slot.id, n, name, phone, email })}
                            className="bg-brand block w-full rounded-lg py-3 text-center font-bold text-white"
                        >
                            確認へ進む
                        </Link>
                    ) : (
                        <button type="button" disabled className="bg-line text-ink-soft block w-full rounded-lg py-3 text-center font-bold">
                            お名前・電話番号・メールアドレスを入力してください
                        </button>
                    )}
                </div>
            )}
        </MobileShell>
    );
}
