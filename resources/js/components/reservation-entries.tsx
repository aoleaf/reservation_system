import { cancelByStaff } from '@/lib/actions';
import { yen } from '@/lib/format';
import type { AdminEntry } from '@/types/pages';
import { Phone } from 'lucide-react';
import { useState } from 'react';
import StatusBadge from './status-badge';

/** 1枠に入っている予約の一覧。各行から電話番号と返金額を確かめて、キャンセルを入力できる（予約一覧とカレンダーのパネルで使う） */
export default function ReservationEntries({ entries }: { entries: AdminEntry[] }) {
    const [canceling, setCanceling] = useState<string | null>(null);
    const [error, setError] = useState<{ id: string; message: string } | null>(null);

    const cancel = (reservationId: string) => {
        setError(null);
        cancelByStaff(
            { reservationId },
            { onError: (e) => setError({ id: reservationId, message: Object.values(e)[0] }), onFinish: () => setCanceling(null) },
        );
    };

    return (
        <ul className="divide-line divide-y text-sm">
            {entries.map((e) => (
                <li key={e.id} className="py-1.5">
                    <div className="flex items-center gap-2">
                        <StatusBadge kind={e.payment} />
                        <span className="min-w-0 flex-1 truncate">{e.name}</span>
                        {e.kind === 'trial' && (
                            <span className="text-ink-soft text-xs whitespace-nowrap">
                                体験 {e.partySize}名・{e.channelLabel}
                            </span>
                        )}
                        <button
                            type="button"
                            onClick={() => setCanceling(canceling === e.id ? null : e.id)}
                            className="text-ink-soft hover:text-alert shrink-0 text-xs underline"
                            aria-expanded={canceling === e.id}
                        >
                            キャンセル
                        </button>
                    </div>
                    {canceling === e.id && (
                        <div className="border-alert/40 bg-alert-soft mt-2 rounded-md border p-2 text-xs">
                            <p className="font-bold">{e.name} さんの予約をキャンセルしますか？</p>
                            {e.phone && (
                                <p className="mt-1 flex items-center gap-1">
                                    <Phone className="h-3 w-3" />
                                    <a href={`tel:${e.phone}`} className="underline">
                                        {e.phone}
                                    </a>
                                </p>
                            )}
                            <p className="mt-1">
                                返金 <span className="font-bold">{yen(e.cancel.refund)}</span>（{e.cancel.note}）
                            </p>
                            <div className="mt-2 flex gap-2">
                                <button type="button" onClick={() => cancel(e.id)} className="bg-alert rounded px-3 py-1 font-bold text-white">
                                    キャンセルを登録
                                </button>
                                <button type="button" onClick={() => setCanceling(null)} className="rounded border bg-white px-3 py-1">
                                    戻る
                                </button>
                            </div>
                        </div>
                    )}
                    {error?.id === e.id && (
                        <p role="alert" className="text-alert mt-1 text-xs font-bold">
                            {error.message}
                        </p>
                    )}
                </li>
            ))}
        </ul>
    );
}
