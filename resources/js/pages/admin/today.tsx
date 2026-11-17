import AdminShell from '@/components/admin-shell';
import SeatMeter from '@/components/seat-meter';
import StatusBadge from '@/components/status-badge';
import { cancelByStaff } from '@/lib/actions';
import { yen } from '@/lib/format';
import type { AdminTodayProps } from '@/types/pages';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Phone, Plus } from 'lucide-react';
import { useState } from 'react';

/** その日の予約一覧。枠ごとに体験・月謝の人と支払いの状態。電話でのキャンセルもここで入力する */
export default function AdminToday({ date, label, isToday, prevDate, nextDate, closed, slots }: AdminTodayProps) {
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
        <AdminShell title="予約一覧" current="today">
            <div className="mb-3 flex items-center justify-between md:mb-5 md:justify-start md:gap-4">
                <Link
                    href={route('admin.today', { date: prevDate })}
                    className="flex h-9 w-9 items-center justify-center rounded-full bg-white"
                    aria-label="前の日"
                >
                    <ChevronLeft className="h-5 w-5" />
                </Link>
                <h2 className="flex items-center gap-2 font-bold md:min-w-48 md:justify-center md:text-lg">
                    {label}
                    {isToday && <span className="bg-brand rounded px-1.5 py-0.5 text-[11px] text-white">今日</span>}
                </h2>
                <Link
                    href={route('admin.today', { date: nextDate })}
                    className="flex h-9 w-9 items-center justify-center rounded-full bg-white"
                    aria-label="次の日"
                >
                    <ChevronRight className="h-5 w-5" />
                </Link>
            </div>

            <Link
                href={route('admin.reservations.new', { date })}
                className="bg-brand mb-4 flex items-center justify-center gap-1 rounded-lg py-2.5 text-sm font-bold text-white md:hidden"
            >
                <Plus className="h-4 w-4" />
                電話・DM・来店の予約を入れる
            </Link>

            {closed && <p className="bg-line/60 mb-3 rounded-lg p-3 text-center text-sm font-bold">この日はお休みです</p>}
            {slots.length === 0 && <p className="text-ink-soft text-sm">この日は枠がありません。</p>}

            <div className="grid gap-3 md:grid-cols-3 md:gap-4">
                {slots.map(({ slot, add, entries }) => (
                    <section key={slot.id} className={'border-line rounded-lg border p-3 ' + (slot.status === 'closed' ? 'bg-line/40' : 'bg-white')}>
                        <div className="mb-2 flex items-center gap-2">
                            <h3 className="font-bold whitespace-nowrap tabular-nums">
                                {slot.start}〜{slot.end}
                            </h3>
                            {slot.status !== 'open' && <StatusBadge kind={slot.status} />}
                        </div>
                        <SeatMeter monthly={slot.used.monthly} trial={slot.used.trial} capacity={slot.capacity} />
                        {entries.length > 0 ? (
                            <ul className="divide-line mt-2 divide-y text-sm">
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
                                                    <button
                                                        type="button"
                                                        onClick={() => cancel(e.id)}
                                                        className="bg-alert rounded px-3 py-1 font-bold text-white"
                                                    >
                                                        キャンセルを登録
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => setCanceling(null)}
                                                        className="rounded border bg-white px-3 py-1"
                                                    >
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
                        ) : (
                            slot.status !== 'closed' && <p className="text-ink-soft mt-2 text-sm">予約はありません</p>
                        )}
                        {add && (
                            <Link
                                href={route('admin.reservations.new', {
                                    date,
                                    slot: slot.id,
                                    ...(add.kind === 'monthly' ? { kind: 'monthly' } : {}),
                                })}
                                className="text-brand mt-2 inline-block text-xs font-bold underline"
                            >
                                この枠に{add.kind === 'monthly' ? '月謝' : '体験'}の予約を入れる（残り{add.remaining}席）
                            </Link>
                        )}
                    </section>
                ))}
            </div>
        </AdminShell>
    );
}
