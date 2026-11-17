import AdminTabs from '@/components/admin-tabs';
import MobileShell from '@/components/mobile-shell';
import MonthCalendar from '@/components/month-calendar';
import SeatMeter from '@/components/seat-meter';
import StatusBadge from '@/components/status-badge';
import { setSlotStatus } from '@/lib/actions';
import type { SlotStatus } from '@/types/models';
import type { AdminSlotsProps } from '@/types/pages';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';

const STATUS_OPTIONS: { value: SlotStatus; label: string }[] = [
    { value: 'open', label: '営業' },
    { value: 'paused', label: '受付停止' },
    { value: 'closed', label: 'お休み' },
];

/** スタッフの月カレンダー。日を選ぶと枠ごとの人数とお休みの切替が出る */
export default function AdminSlots({ month, monthLabel, prevMonth, nextMonth, leadingBlanks, initialDate, days }: AdminSlotsProps) {
    const [selected, setSelected] = useState(initialDate);
    const [error, setError] = useState<{ slotId: string; message: string } | null>(null);
    const day = days.find((d) => d.date === selected) ?? days[0];

    const change = (slotId: string, status: SlotStatus) => {
        setError(null);
        setSlotStatus({ slotId, status }, { onError: (e) => setError({ slotId, message: Object.values(e)[0] }) });
    };

    return (
        <MobileShell title="カレンダー" variant="staff" footer={<AdminTabs current="slots" />}>
            <div className="mb-3 flex items-center justify-between">
                <Link href={route('admin.slots', { month: prevMonth })} className="flex h-9 w-9 items-center justify-center rounded-full bg-white" aria-label="前の月">
                    <ChevronLeft className="h-5 w-5" />
                </Link>
                <h2 className="font-bold">{monthLabel}</h2>
                <Link href={route('admin.slots', { month: nextMonth })} className="flex h-9 w-9 items-center justify-center rounded-full bg-white" aria-label="次の月">
                    <ChevronRight className="h-5 w-5" />
                </Link>
            </div>

            <MonthCalendar
                key={month}
                leadingBlanks={leadingBlanks}
                days={days}
                renderDay={(d) => (
                    <button
                        type="button"
                        onClick={() => setSelected(d.date)}
                        className={
                            'flex h-16 w-full flex-col items-center gap-0.5 rounded-md border pt-1 text-xs ' +
                            (d.date === selected ? 'border-brand ring-brand bg-white ring-2 ' : 'border-line ') +
                            (d.closed ? 'bg-line/70 text-ink-soft' : d.date === selected ? '' : 'bg-white')
                        }
                        aria-label={d.label}
                        aria-pressed={d.date === selected}
                    >
                        <span className={'tabular-nums ' + (d.isToday ? 'bg-brand rounded-full px-1.5 font-bold text-white' : '')}>{d.day}</span>
                        {d.closed ? (
                            <span className="text-[10px]">休</span>
                        ) : (
                            d.slots.map((s) => (
                                <span key={s.id} className={'flex ' + (s.status === 'closed' ? 'opacity-30' : '')}>
                                    <SeatMeter monthly={s.used.monthly} trial={s.used.trial} capacity={s.capacity} compact />
                                </span>
                            ))
                        )}
                    </button>
                )}
            />

            {day && (
                <section className="mt-5">
                    <div className="mb-2 flex items-center justify-between">
                        <h3 className="font-bold">{day.label}</h3>
                        <Link href={route('admin.today', { date: day.date })} className="text-brand text-sm underline">
                            この日の予約一覧
                        </Link>
                    </div>
                    {day.slots.length === 0 && <p className="text-ink-soft text-sm">この日は枠がありません。</p>}
                    <ul className="space-y-2">
                        {day.slots.map((s) => (
                            <li key={s.id} className={'border-line rounded-lg border p-3 ' + (s.status === 'closed' ? 'bg-line/50' : 'bg-white')}>
                                <div className="flex items-center gap-2">
                                    <span className="font-bold whitespace-nowrap tabular-nums">
                                        {s.start}〜{s.end}
                                    </span>
                                    {s.status !== 'open' && <StatusBadge kind={s.status} />}
                                    <div className="border-line ml-auto flex overflow-hidden rounded-md border text-xs" role="group" aria-label="枠の状態">
                                        {STATUS_OPTIONS.map((o) => (
                                            <button
                                                key={o.value}
                                                type="button"
                                                aria-pressed={s.status === o.value}
                                                onClick={() => change(s.id, o.value)}
                                                className={'px-2 py-1 ' + (s.status === o.value ? 'bg-brand font-bold text-white' : 'text-ink-soft bg-white')}
                                            >
                                                {o.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                                <div className="mt-2">
                                    <SeatMeter monthly={s.used.monthly} trial={s.used.trial} capacity={s.capacity} />
                                </div>
                                {error?.slotId === s.id && (
                                    <p role="alert" className="text-alert mt-2 text-xs font-bold">
                                        {error.message}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </MobileShell>
    );
}
