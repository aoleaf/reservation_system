import MobileShell from '@/components/mobile-shell';
import MonthCalendar from '@/components/month-calendar';
import SlotCard from '@/components/slot-card';
import { yen } from '@/lib/format';
import type { AvailabilityMark, ReserveIndexProps } from '@/types/pages';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';

const MARK: Record<AvailabilityMark, { sign: string; label: string; className: string }> = {
    ok: { sign: '○', label: '空きあり', className: 'text-brand' },
    few: { sign: '△', label: '残りわずか', className: 'text-onsite' },
    full: { sign: '×', label: '満席', className: 'text-alert' },
    none: { sign: '−', label: '受付なし', className: 'text-ink-soft/50' },
};

/** 体験レッスンの空き状況（○△×）のカレンダー。日を選ぶと、その日の枠が出る */
export default function ReserveIndex({ unitPrice, note, monthLabel, prevMonth, nextMonth, leadingBlanks, days, initialDate }: ReserveIndexProps) {
    const [selected, setSelected] = useState(initialDate);
    const day = days.find((d) => d.date === selected && d.mark !== 'none');

    const nav = (month: string | null, label: string, Icon: typeof ChevronLeft) =>
        month ? (
            <Link href={route('reserve.index', { month })} className="flex h-9 w-9 items-center justify-center rounded-full bg-white" aria-label={label}>
                <Icon className="h-5 w-5" />
            </Link>
        ) : (
            <span className="h-9 w-9" />
        );

    return (
        <MobileShell title="体験レッスンの予約" back={route('demo')}>
            <div className="mb-4 text-sm leading-relaxed">
                <p>
                    体験レッスン 1名 <span className="font-bold">{yen(unitPrice)}</span>（2時間）
                </p>
                <p className="text-ink-soft mt-1">{note}</p>
            </div>

            <div className="mb-2 flex items-center justify-between">
                {nav(prevMonth, '前の月', ChevronLeft)}
                <h2 className="font-mincho font-bold">{monthLabel}</h2>
                {nav(nextMonth, '次の月', ChevronRight)}
            </div>

            <MonthCalendar
                leadingBlanks={leadingBlanks}
                days={days}
                renderDay={(d) => {
                    const m = MARK[d.mark];
                    return (
                        <button
                            type="button"
                            disabled={d.mark === 'none'}
                            onClick={() => setSelected(d.date)}
                            aria-label={`${d.label} ${m.label}`}
                            aria-pressed={d.date === selected}
                            className={
                                'flex h-14 w-full flex-col items-center justify-center rounded-md border text-sm tabular-nums ' +
                                (d.mark === 'none' ? 'text-ink-soft/50 border-transparent' : 'border-line bg-white ') +
                                (d.date === selected ? 'ring-brand border-brand ring-2' : '')
                            }
                        >
                            <span>{d.day}</span>
                            <span className={'text-base leading-none font-bold ' + m.className}>{m.sign}</span>
                        </button>
                    );
                }}
            />
            <p className="text-ink-soft mt-2 flex flex-wrap gap-x-3 text-xs">
                {(['ok', 'few', 'full'] as const).map((k) => (
                    <span key={k}>
                        <span className={'font-bold ' + MARK[k].className}>{MARK[k].sign}</span> {MARK[k].label}
                    </span>
                ))}
            </p>

            <section className="mt-5">
                {!day ? (
                    <p className="text-ink-soft text-sm">カレンダーから日を選んでください。</p>
                ) : (
                    <>
                        <h3 className="font-mincho mb-2 font-bold">{day.label}</h3>
                        <div className="space-y-2">
                            {day.slots.map((s) => (
                                <SlotCard key={s.id} slot={s} href={route('reserve.form', { slot: s.id })} showMeter={false} />
                            ))}
                        </div>
                    </>
                )}
            </section>
        </MobileShell>
    );
}
