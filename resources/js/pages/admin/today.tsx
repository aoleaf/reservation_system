import AdminTabs from '@/components/admin-tabs';
import MobileShell from '@/components/mobile-shell';
import SeatMeter from '@/components/seat-meter';
import StatusBadge from '@/components/status-badge';
import type { AdminTodayProps } from '@/types/pages';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';

/** その日の予約一覧。枠ごとに体験・月謝の人と支払いの状態 */
export default function AdminToday({ date, label, isToday, prevDate, nextDate, closed, slots }: AdminTodayProps) {
    return (
        <MobileShell title="今日の予約" variant="staff" footer={<AdminTabs current="today" />}>
            <div className="mb-3 flex items-center justify-between">
                <Link href={route('admin.today', { date: prevDate })} className="flex h-9 w-9 items-center justify-center rounded-full bg-white" aria-label="前の日">
                    <ChevronLeft className="h-5 w-5" />
                </Link>
                <h2 className="flex items-center gap-2 font-bold">
                    {label}
                    {isToday && <span className="bg-brand rounded px-1.5 py-0.5 text-[11px] text-white">今日</span>}
                </h2>
                <Link href={route('admin.today', { date: nextDate })} className="flex h-9 w-9 items-center justify-center rounded-full bg-white" aria-label="次の日">
                    <ChevronRight className="h-5 w-5" />
                </Link>
            </div>

            <Link href={route('admin.reservations.new', { date })} className="bg-brand mb-4 flex items-center justify-center gap-1 rounded-lg py-2.5 text-sm font-bold text-white">
                <Plus className="h-4 w-4" />
                電話・DMの予約を入れる
            </Link>

            {closed && <p className="bg-line/60 mb-3 rounded-lg p-3 text-center text-sm font-bold">この日はお休みです</p>}
            {slots.length === 0 && <p className="text-ink-soft text-sm">この日は枠がありません。</p>}

            <div className="space-y-3">
                {slots.map(({ slot, entries }) => (
                    <section key={slot.id} className={'border-line rounded-lg border p-3 ' + (slot.blocked ? 'bg-line/40' : 'bg-white')}>
                        <div className="mb-2 flex items-center gap-2">
                            <h3 className="font-bold whitespace-nowrap tabular-nums">
                                {slot.start}〜{slot.end}
                            </h3>
                            {slot.blocked && <StatusBadge kind="closed" />}
                        </div>
                        <SeatMeter monthly={slot.used.monthly} trial={slot.used.trial} capacity={slot.capacity} />
                        {entries.length > 0 ? (
                            <ul className="divide-line mt-2 divide-y text-sm">
                                {entries.map((e) => (
                                    <li key={e.id} className="flex items-center gap-2 py-1.5">
                                        <StatusBadge kind={e.payment} />
                                        <span className="min-w-0 flex-1 truncate">{e.name}</span>
                                        {e.kind === 'trial' && <span className="text-ink-soft text-xs whitespace-nowrap">体験 {e.partySize}名・{e.channelLabel}</span>}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            !slot.blocked && <p className="text-ink-soft mt-2 text-sm">予約はありません</p>
                        )}
                        {slot.bookable && (
                            <Link href={route('admin.reservations.new', { date, slot: slot.id })} className="text-brand mt-2 inline-block text-xs font-bold underline">
                                この枠に体験の予約を入れる（残り{slot.remaining}席）
                            </Link>
                        )}
                    </section>
                ))}
            </div>
        </MobileShell>
    );
}
