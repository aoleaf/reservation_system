import AdminShell from '@/components/admin-shell';
import { updateSettings } from '@/lib/actions';
import { yen } from '@/lib/format';
import type { AdminSettingsProps } from '@/types/pages';
import { useState } from 'react';

/** 設定：体験の料金（1名あたり） */
export default function AdminSettings({ trialPrice }: AdminSettingsProps) {
    const [price, setPrice] = useState(String(trialPrice));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saved, setSaved] = useState(false);
    const [busy, setBusy] = useState(false);
    const value = Number(price);
    const changed = price.trim() !== '' && value !== trialPrice;

    const save = () => {
        setBusy(true);
        setErrors({});
        setSaved(false);
        let failed = false;
        updateSettings(
            { trialPrice: value },
            {
                onError: (e) => {
                    failed = true;
                    setErrors(e);
                },
                onFinish: () => {
                    setBusy(false);
                    if (!failed) setSaved(true);
                },
            },
        );
    };

    return (
        <AdminShell title="設定" current="settings">
            <section className="border-line max-w-md rounded-lg border bg-white p-5">
                <h2 className="mb-1 font-bold">体験レッスンの料金</h2>
                <p className="text-ink-soft mb-4 text-sm">今の料金：1名 {yen(trialPrice)}</p>
                <label className="block">
                    <span className="mb-1 block text-sm">1名あたり（円）</span>
                    <input
                        type="number"
                        inputMode="numeric"
                        min={100}
                        step={100}
                        value={price}
                        onChange={(e) => {
                            setPrice(e.target.value);
                            setSaved(false);
                        }}
                        className="border-line focus:border-brand w-40 rounded-md border bg-white px-3 py-2 text-base tabular-nums focus:outline-none"
                    />
                </label>
                <p className="text-ink-soft mt-3 text-xs leading-relaxed">
                    新しい料金は、これから入る予約から使います。もう入っている予約の金額は変わりません（返金も、その予約で払った金額をもとに計算します）。
                </p>
                {Object.values(errors).map((m) => (
                    <p key={m} role="alert" className="text-alert mt-3 text-sm font-bold">
                        {m}
                    </p>
                ))}
                {saved && (
                    <p role="status" className="text-paid mt-3 text-sm font-bold">
                        保存しました
                    </p>
                )}
                <button
                    type="button"
                    onClick={save}
                    disabled={!changed || busy}
                    className="bg-brand disabled:bg-line disabled:text-ink-soft mt-4 rounded-lg px-6 py-2.5 font-bold text-white"
                >
                    保存する
                </button>
            </section>
        </AdminShell>
    );
}
