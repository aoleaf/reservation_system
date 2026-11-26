import AdminShell from '@/components/admin-shell';
import { chooseLineGroup, testLine, updateSettings } from '@/lib/actions';
import { yen } from '@/lib/format';
import type { AdminSettingsProps } from '@/types/pages';
import { useState } from 'react';

/** 設定：体験の料金（1名あたり） */
export default function AdminSettings({ trialPrice, line, notice, errors: pageErrors }: AdminSettingsProps) {
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

            <LineSection line={line} notice={notice ?? null} errors={pageErrors ?? {}} />
        </AdminShell>
    );
}

/** LINE 通知：送り先のグループ（招待したグループを候補として受け取り、ここで決める）とテスト送信 */
function LineSection({ line, notice, errors }: { line: AdminSettingsProps['line']; notice: string | null; errors: Record<string, string> }) {
    const [busy, setBusy] = useState(false);
    const [localErrors, setLocalErrors] = useState<Record<string, string>>({});
    const messages = Object.values({ ...errors, ...localErrors }).filter((m) => m);
    const run = (action: typeof testLine) => {
        setBusy(true);
        setLocalErrors({});
        action({}, { onError: setLocalErrors, onFinish: () => setBusy(false) });
    };

    return (
        <section className="border-line mt-6 max-w-md rounded-lg border bg-white p-5 text-sm">
            <h2 className="mb-1 text-base font-bold">LINE 通知</h2>
            <p className="text-ink-soft mb-4 text-xs leading-relaxed">
                毎朝 {line.digestAt} に、その日の予約をお店の LINE グループに送ります。そのあとに今日の予約が入ったら、その予約だけを送ります。
            </p>
            {!line.configured && (
                <p className="bg-onsite-soft text-onsite mb-3 rounded p-2 text-xs font-bold">
                    LINE のトークンが設定されていないので、今は送らずに記録だけしています（.env の LINE_CHANNEL_ACCESS_TOKEN）。
                </p>
            )}
            <dl className="space-y-1">
                <div className="flex gap-2">
                    <dt className="text-ink-soft w-28 shrink-0">送り先のグループ</dt>
                    <dd className={line.group ? 'font-bold' : 'text-alert font-bold'}>{line.group ?? '未設定'}</dd>
                </div>
                <div className="flex gap-2">
                    <dt className="text-ink-soft w-28 shrink-0">最後のまとめ</dt>
                    <dd>{line.lastSentAt ?? 'まだ送っていません'}</dd>
                </div>
            </dl>
            {line.candidate ? (
                <div className="border-brand/40 bg-brand-faint mt-4 rounded p-3">
                    <p>
                        招待されたグループがあります（{line.candidate.id}・{line.candidate.at}
                        ）。お店のグループで招待したものなら、送り先にしてください。
                    </p>
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => run(chooseLineGroup)}
                        className="bg-brand mt-2 rounded px-4 py-1.5 font-bold text-white disabled:opacity-60"
                    >
                        このグループに送る
                    </button>
                </div>
            ) : (
                !line.group && (
                    <p className="text-ink-soft mt-4 text-xs">LINE の公式アカウントを、お店の LINE グループに招待すると、ここに候補が出ます。</p>
                )
            )}
            {line.group && (
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => run(testLine)}
                    className="border-brand text-brand mt-4 rounded border px-4 py-1.5 font-bold disabled:opacity-60"
                >
                    テスト送信
                </button>
            )}
            {notice && (
                <p role="status" className="text-paid mt-3 text-xs font-bold">
                    {notice}
                </p>
            )}
            {messages.map((m) => (
                <p key={m} role="alert" className="text-alert mt-3 text-xs font-bold">
                    {m}
                </p>
            ))}
        </section>
    );
}
