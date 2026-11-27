import AdminShell from '@/components/admin-shell';
import RefundIssues from '@/components/refund-issues';
import ReservationEntries from '@/components/reservation-entries';
import SeatMeter from '@/components/seat-meter';
import StatusBadge from '@/components/status-badge';
import type { AdminTodayProps } from '@/types/pages';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';

/** その日の予約一覧。枠ごとに体験・月謝の人と支払いの状態。電話でのキャンセルもここで入力する */
export default function AdminToday({ refundIssues, date, label, isToday, prevDate, nextDate, closed, slots }: AdminTodayProps) {
    return (
        <AdminShell title="予約一覧" current="today">
            <RefundIssues issues={refundIssues} />
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
                            <div className="mt-2">
                                <ReservationEntries entries={entries} />
                            </div>
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
