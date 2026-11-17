import AdminShell from '@/components/admin-shell';
import MonthCalendar from '@/components/month-calendar';
import SeatMeter from '@/components/seat-meter';
import SlotBar from '@/components/slot-bar';
import StatusBadge from '@/components/status-badge';
import { setSlotStatus } from '@/lib/actions';
import type { SlotStatus } from '@/types/models';
import type { AdminSlotCell, AdminSlotsProps } from '@/types/pages';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useRef, useState } from 'react';

const STATUS_OPTIONS: { value: SlotStatus; label: string }[] = [
    { value: 'open', label: '営業' },
    { value: 'paused', label: '受付停止' },
    { value: 'closed', label: 'お休み' },
];

/** 1枠ぶんの行：内訳の帯と「4/6」。スマホ幅では数字を出さず帯だけにする */
function SlotRow({ s }: { s: AdminSlotCell }) {
    return (
        <span className="flex items-center gap-1" title={`${s.start} 月謝${s.used.monthly}・体験${s.used.trial}／${s.capacity}`}>
            <SlotBar monthly={s.used.monthly} trial={s.used.trial} capacity={s.capacity} trialOnlyFree={s.trialOnlyFree} status={s.status} />
            <span
                className={'hidden w-6 shrink-0 text-right text-[10px] tabular-nums sm:inline ' + (s.status === 'closed' ? 'text-ink-soft/60' : '')}
            >
                {s.status === 'closed' ? '休' : `${s.usedTotal}/${s.capacity}`}
            </span>
        </span>
    );
}

/** スタッフのカレンダー。常に2か月分。日を選ぶと、枠ごとの内訳と状態（営業・受付停止・お休み）の切替が出る */
export default function AdminSlots({ prevMonth, nextMonth, months, initialDate }: AdminSlotsProps) {
    const [selected, setSelected] = useState(initialDate);
    const detailRef = useRef<HTMLElement>(null);
    // 日を選んだら、その日の内訳までスクロールする（スマホではカレンダーの下にあって見えないため）
    const select = (date: string) => {
        setSelected(date);
        requestAnimationFrame(() => detailRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    };
    const [error, setError] = useState<{ slotId: string; message: string } | null>(null);
    const day = months.flatMap((m) => m.days).find((d) => d.date === selected);

    const change = (slotId: string, status: SlotStatus) => {
        setError(null);
        setSlotStatus({ slotId, status }, { onError: (e) => setError({ slotId, message: Object.values(e)[0] }) });
    };

    const navBtn = 'flex h-9 items-center gap-1 rounded-full bg-white px-3 text-sm';

    return (
        <AdminShell
            title="カレンダー"
            current="slots"
            actions={
                <div className="flex gap-2">
                    <Link href={route('admin.slots', { month: prevMonth })} className={navBtn} aria-label="前の月">
                        <ChevronLeft className="h-4 w-4" />
                        <span className="hidden sm:inline">前の月</span>
                    </Link>
                    <Link href={route('admin.slots', { month: nextMonth })} className={navBtn} aria-label="次の月">
                        <span className="hidden sm:inline">次の月</span>
                        <ChevronRight className="h-4 w-4" />
                    </Link>
                </div>
            }
        >
            <p className="text-ink-soft mb-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                <span className="flex items-center gap-1">
                    <span className="bg-brand inline-block h-2.5 w-4 rounded-sm" />
                    月謝
                </span>
                <span className="flex items-center gap-1">
                    <span className="bg-brand-soft inline-block h-2.5 w-4 rounded-sm" />
                    体験
                </span>
                <span className="flex items-center gap-1">
                    <span className="bg-hatch inline-block h-2.5 w-4 rounded-sm" />
                    体験のみ可（月謝が上限）
                </span>
                <span className="flex items-center gap-1">
                    <span className="border-line inline-block h-2.5 w-4 rounded-sm border bg-white" />
                    空き
                </span>
                <span className="flex items-center gap-1">
                    <span className="outline-onsite inline-block h-2.5 w-4 rounded-sm bg-white outline-2 outline-dashed" />
                    受付停止
                </span>
                <span className="flex items-center gap-1">
                    <span className="bg-line inline-block h-2.5 w-4 rounded-sm" />
                    お休み
                </span>
                <span>各日：上から 10:00 / 13:00 / 15:30</span>
            </p>

            <div className="grid gap-6 lg:grid-cols-2">
                {months.map((m) => (
                    <section key={m.month}>
                        <h2 className="mb-2 font-bold">{m.label}</h2>
                        <MonthCalendar
                            leadingBlanks={m.leadingBlanks}
                            days={m.days}
                            renderDay={(d) => (
                                <button
                                    type="button"
                                    onClick={() => select(d.date)}
                                    aria-label={d.label}
                                    aria-pressed={d.date === selected}
                                    className={
                                        'flex w-full flex-col gap-1 rounded-md border px-1 pt-0.5 pb-1.5 text-left ' +
                                        (d.date === selected ? 'border-brand ring-brand ring-2' : 'border-line') +
                                        (d.closed ? 'bg-line/50' : 'bg-white')
                                    }
                                >
                                    <span
                                        className={
                                            'self-center text-xs tabular-nums ' +
                                            (d.isToday ? 'bg-brand rounded-full px-1.5 font-bold text-white' : '')
                                        }
                                    >
                                        {d.day}
                                    </span>
                                    {d.slots.map((s) => (
                                        <SlotRow key={s.id} s={s} />
                                    ))}
                                </button>
                            )}
                        />
                    </section>
                ))}
            </div>

            {day && (
                <section ref={detailRef} className="mt-8 scroll-mt-20">
                    <div className="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1">
                        <h3 className="text-lg font-bold">{day.label}</h3>
                        <Link href={route('admin.today', { date: day.date })} className="text-brand text-sm underline">
                            この日の予約一覧（キャンセルの入力）
                        </Link>
                    </div>
                    {day.slots.length === 0 && <p className="text-ink-soft text-sm">この日は枠がありません。</p>}
                    <ul className="grid gap-3 md:grid-cols-3">
                        {day.slots.map((s) => (
                            <li key={s.id} className={'border-line rounded-lg border p-3 ' + (s.status === 'closed' ? 'bg-line/50' : 'bg-white')}>
                                <div className="mb-2 flex items-center gap-2">
                                    <span className="font-bold whitespace-nowrap tabular-nums">
                                        {s.start}〜{s.end}
                                    </span>
                                    {s.status !== 'open' && <StatusBadge kind={s.status} />}
                                </div>
                                <SeatMeter monthly={s.used.monthly} trial={s.used.trial} capacity={s.capacity} />
                                <div className="border-line mt-3 flex overflow-hidden rounded-md border text-xs" role="group" aria-label="枠の状態">
                                    {STATUS_OPTIONS.map((o) => {
                                        const locked = o.value === 'closed' && !s.canClose && s.status !== 'closed';
                                        return (
                                            <button
                                                key={o.value}
                                                type="button"
                                                aria-pressed={s.status === o.value}
                                                disabled={locked}
                                                onClick={() => change(s.id, o.value)}
                                                className={
                                                    'flex-1 py-1.5 ' +
                                                    (s.status === o.value
                                                        ? 'bg-brand font-bold text-white'
                                                        : locked
                                                          ? 'text-ink-soft/40 bg-white'
                                                          : 'text-ink-soft bg-white')
                                                }
                                            >
                                                {o.label}
                                            </button>
                                        );
                                    })}
                                </div>
                                {!s.canClose && s.status !== 'closed' && (
                                    <p className="text-ink-soft mt-2 text-xs leading-relaxed">
                                        予約が{s.reservationCount}件あるため、お休みにはできません。
                                        {s.status === 'open' ? '先に「受付停止」にして、' : ''}
                                        お客様に連絡してキャンセルを入力すると、お休みにできます。
                                    </p>
                                )}
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
        </AdminShell>
    );
}
