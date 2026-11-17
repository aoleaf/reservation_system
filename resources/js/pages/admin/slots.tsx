import AdminShell from '@/components/admin-shell';
import MonthCalendar from '@/components/month-calendar';
import SeatMeter from '@/components/seat-meter';
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

/** 埋まり具合の濃さ（0=空き 〜 4=満席）。明るさでも区別できるよう、濃い2段は文字を白に */
const FILL: Record<AdminSlotCell['fill'], string> = {
    0: 'bg-white text-ink-soft',
    1: 'bg-brand/15 text-ink',
    2: 'bg-brand/35 text-ink',
    3: 'bg-brand/70 text-white',
    4: 'bg-brand text-white font-bold',
};

/** 1枠ぶんの小さな行（時間と「4/6」）。受付停止・お休みは斜線とグレーで示す */
function SlotChip({ s }: { s: AdminSlotCell }) {
    const title =
        `${s.start} 月謝${s.used.monthly}・体験${s.used.trial}／${s.capacity}` +
        (s.status === 'paused' ? '（受付停止）' : s.status === 'closed' ? '（お休み）' : '');
    if (s.status === 'closed') {
        return (
            <span
                title={title}
                className="bg-line/80 text-ink-soft/70 flex items-center justify-between rounded-sm px-1 text-[10px] leading-4 line-through"
            >
                <span className="hidden sm:inline">{s.start.slice(0, 2)}</span>
                <span>休</span>
            </span>
        );
    }
    return (
        <span
            title={title}
            className={
                'flex items-center justify-between rounded-sm px-1 text-[10px] leading-4 tabular-nums ' +
                FILL[s.fill] +
                (s.status === 'paused' ? ' outline-onsite outline-2 -outline-offset-2 outline-dashed' : '')
            }
        >
            <span className="hidden opacity-70 sm:inline">{s.start.slice(0, 2)}</span>
            <span>
                {s.usedTotal}/{s.capacity}
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
            <p className="text-ink-soft mb-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                <span>埋まり具合（席数／定員）：</span>
                {([0, 1, 2, 3, 4] as const).map((f) => (
                    <span key={f} className={'rounded-sm px-1.5 leading-5 ' + FILL[f]}>
                        {['空き', '1〜2', '3〜4', '5', '満席'][f]}
                    </span>
                ))}
                <span className="outline-onsite rounded-sm px-1.5 leading-5 outline-2 -outline-offset-2 outline-dashed">受付停止</span>
                <span className="bg-line/80 rounded-sm px-1.5 leading-5 line-through">休</span>
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
                                        'flex w-full flex-col gap-px rounded-md border p-0.5 text-left ' +
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
                                        <SlotChip key={s.id} s={s} />
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
