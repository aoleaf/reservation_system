import MobileShell from '@/components/mobile-shell';
import NotFound from '@/components/not-found';
import { cancelByToken } from '@/lib/actions';
import { yen } from '@/lib/format';
import type { ReserveCancelProps } from '@/types/pages';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

/** キャンセル（返金額は今日の日付で変わる） */
export default function ReserveCancel({ reservation, slotLabel, token, todayLabel, refund, refundReason, cancelPolicy }: ReserveCancelProps) {
    const [errors, setErrors] = useState<Record<string, string>>({});

    if (!reservation) {
        return (
            <MobileShell title="予約のキャンセル">
                <NotFound message="予約が見つかりません" backHref={route('reserve.index')} backLabel="枠の一覧へ戻る" />
            </MobileShell>
        );
    }

    return (
        <MobileShell title="予約のキャンセル">
            <dl className="border-line divide-line divide-y rounded-lg border bg-white text-sm">
                {[
                    ['予約番号', reservation.id],
                    ['日時', slotLabel],
                    ['人数', `${reservation.partySize}名`],
                    ['お名前', reservation.name],
                    ['お支払い', yen(reservation.amount)],
                ].map(([k, v]) => (
                    <div key={k} className="flex gap-3 px-3 py-2.5">
                        <dt className="text-ink-soft w-20 shrink-0">{k}</dt>
                        <dd>{v}</dd>
                    </div>
                ))}
            </dl>

            {reservation.canceled ? (
                <div className="bg-line/60 mt-5 rounded-lg p-4 text-center">
                    <p className="font-bold">キャンセルしました</p>
                    <p className="mt-1 text-sm">返金額 {yen(reservation.refundedAmount ?? 0)}</p>
                </div>
            ) : (
                <>
                    <div className="bg-brand-faint mt-5 rounded-lg p-4 text-center">
                        <p className="text-ink-soft text-xs">今日（{todayLabel}）キャンセルした場合</p>
                        <p className="text-brand mt-1 text-2xl font-bold">返金 {yen(refund)}</p>
                        <p className="mt-1 text-sm">{refundReason}</p>
                    </div>
                    <ul className="text-ink-soft mt-4 list-disc space-y-1 pl-5 text-sm">
                        {cancelPolicy.map((p) => (
                            <li key={p}>{p}</li>
                        ))}
                    </ul>
                    {Object.values(errors).map((e) => (
                        <p key={e} role="alert" className="text-alert mt-3 text-sm font-bold">
                            {e}
                        </p>
                    ))}
                    <button
                        type="button"
                        onClick={() => {
                            if (confirm('この予約をキャンセルします。よろしいですか？')) cancelByToken({ token }, { onError: setErrors });
                        }}
                        className="bg-alert mt-6 block w-full rounded-lg py-3 font-bold text-white"
                    >
                        キャンセルする
                    </button>
                </>
            )}
            <Link href={route('reserve.index')} className="text-brand mt-4 block text-center text-sm underline">
                枠の一覧へ
            </Link>
        </MobileShell>
    );
}
