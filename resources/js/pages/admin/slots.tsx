import AdminShell from '@/components/admin-shell';
import MonthCalendar from '@/components/month-calendar';
import ReservationEntries from '@/components/reservation-entries';
import SlotBar from '@/components/slot-bar';
import StatusBadge from '@/components/status-badge';
import { setSlotStatus } from '@/lib/actions';
import type { SlotStatus } from '@/types/models';
import type { AdminEntry, AdminSlotCell, AdminSlotsProps } from '@/types/pages';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useState } from 'react';

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

/** パネルの1枠：内訳・予約者（電話・キャンセル）・状態の切替 */
function PanelSlot({ s }: { s: AdminSlotCell & { entries: AdminEntry[] } }) {
    const [error, setError] = useState<string | null>(null);
    const change = (status: SlotStatus) => {
        setError(null);
        setSlotStatus({ slotId: s.id, status }, { onError: (e) => setError(Object.values(e)[0]) });
    };
    return (
        <section className={'border-line border-b py-4 last:border-b-0 ' + (s.status === 'closed' ? 'opacity-70' : '')}>
            <div className="mb-2 flex flex-wrap items-center gap-2">
                <span className="font-bold whitespace-nowrap tabular-nums">
                    {s.start}〜{s.end}
                </span>
                {s.status !== 'open' && <StatusBadge kind={s.status} />}
                {s.mark && <StatusBadge kind={s.mark} />}
                <span className="text-ink-soft ml-auto text-xs whitespace-nowrap">
                    月謝{s.used.monthly}・体験{s.used.trial}／{s.capacity}
                </span>
            </div>
            <SlotBar
                monthly={s.used.monthly}
                trial={s.used.trial}
                capacity={s.capacity}
                trialOnlyFree={s.trialOnlyFree}
                status={s.status}
                size="md"
            />
            {s.entries.length > 0 ? (
                <div className="mt-2">
                    <ReservationEntries entries={s.entries} />
                </div>
            ) : (
                s.status !== 'closed' && <p className="text-ink-soft mt-2 text-sm">予約はありません</p>
            )}
            <div className="border-line mt-3 flex overflow-hidden rounded-md border text-xs" role="group" aria-label={`${s.start} の状態`}>
                {STATUS_OPTIONS.map((o) => {
                    const locked = o.value === 'closed' && !s.canClose && s.status !== 'closed';
                    return (
                        <button
                            key={o.value}
                            type="button"
                            aria-pressed={s.status === o.value}
                            disabled={locked}
                            onClick={() => change(o.value)}
                            className={
                                'flex-1 py-1.5 whitespace-nowrap ' +
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
                    予約があと{s.reservationCount}件。{s.status === 'open' ? '「受付停止」にしてから、' : ''}
                    連絡してキャンセルを入力すると、お休みにできます。
                </p>
            )}
            {error && (
                <p role="alert" className="text-alert mt-2 text-xs font-bold">
                    {error}
                </p>
            )}
        </section>
    );
}

/**
 * スタッフのカレンダー。常に2か月分。日を押すと、その日の予約・キャンセル・状態の切替をパネルで行う。
 * パネルは 1440px 以上ではカレンダーの横に並べ、それより狭いと右から（スマホは下から）重ねて出す。
 */
export default function AdminSlots({ month, prevMonth, nextMonth, months, selected }: AdminSlotsProps) {
    const keep = { preserveScroll: true, preserveState: true } as const;
    const closeHref = route('admin.slots', { month });
    const navBtn = 'flex h-9 items-center gap-1 rounded-full bg-white px-3 text-sm';

    return (
        <AdminShell
            title="カレンダー"
            current="slots"
            wide
            actions={
                <div className="flex gap-2">
                    <Link
                        href={route('admin.slots', { month: prevMonth, ...(selected ? { date: selected.date } : {}) })}
                        {...keep}
                        className={navBtn}
                        aria-label="前の月"
                    >
                        <ChevronLeft className="h-4 w-4" />
                        <span className="hidden sm:inline">前の月</span>
                    </Link>
                    <Link
                        href={route('admin.slots', { month: nextMonth, ...(selected ? { date: selected.date } : {}) })}
                        {...keep}
                        className={navBtn}
                        aria-label="次の月"
                    >
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

            <div className="min-[1440px]:flex min-[1440px]:items-start min-[1440px]:gap-8">
                <div className="grid flex-1 gap-6 lg:grid-cols-2">
                    {months.map((m) => (
                        <section key={m.month}>
                            <h2 className="mb-2 font-bold">{m.label}</h2>
                            <MonthCalendar
                                leadingBlanks={m.leadingBlanks}
                                days={m.days}
                                renderDay={(d) => (
                                    <Link
                                        href={route('admin.slots', { month, date: d.date })}
                                        {...keep}
                                        aria-label={d.label}
                                        aria-current={d.date === selected?.date ? 'date' : undefined}
                                        className={
                                            'flex w-full flex-col gap-1 rounded-md border px-1 pt-0.5 pb-1.5 text-left ' +
                                            (d.date === selected?.date ? 'border-brand ring-brand ring-2' : 'border-line') +
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
                                    </Link>
                                )}
                            />
                        </section>
                    ))}
                </div>

                {/* 広い画面で何も選んでいないときの案内 */}
                {!selected && (
                    <aside className="border-line text-ink-soft hidden w-[400px] shrink-0 rounded-xl border bg-white p-5 text-sm min-[1440px]:block">
                        日を選ぶと、その日の予約（電話・キャンセル）と、枠の状態の切替がここに出ます。
                    </aside>
                )}

                {selected && (
                    <>
                        {/* 狭い画面ではカレンダーの上に重ねるので、外側を押したら閉じる */}
                        <Link href={closeHref} {...keep} aria-label="パネルを閉じる" className="fixed inset-0 z-30 bg-black/25 min-[1440px]:hidden" />
                        <aside
                            aria-label={`${selected.label}の予約と状態`}
                            className={
                                'fixed inset-x-0 bottom-0 z-40 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white px-5 pb-8 shadow-2xl ' +
                                'md:inset-y-0 md:top-14 md:right-0 md:left-auto md:max-h-none md:w-[420px] md:rounded-none' +
                                'min-[1440px]:border-line min-[1440px]:static min-[1440px]:sticky min-[1440px]:top-20 min-[1440px]:z-auto min-[1440px]:max-h-[calc(100vh-7rem)] min-[1440px]:w-[400px] min-[1440px]:shrink-0 min-[1440px]:rounded-xl min-[1440px]:border min-[1440px]:shadow-none'
                            }
                        >
                            <div className="border-line sticky top-0 z-10 -mx-5 flex items-center gap-2 border-b bg-white px-5 py-3">
                                <h3 className="text-lg font-bold">{selected.label}</h3>
                                <Link
                                    href={closeHref}
                                    {...keep}
                                    aria-label="閉じる"
                                    className="hover:bg-line/50 ml-auto flex h-8 w-8 items-center justify-center rounded-full"
                                >
                                    <X className="h-4 w-4" />
                                </Link>
                            </div>
                            <Link href={route('admin.today', { date: selected.date })} className="text-brand mt-3 inline-block text-sm underline">
                                この日の予約一覧を開く
                            </Link>
                            {selected.slots.length === 0 ? (
                                <p className="text-ink-soft mt-4 text-sm">この日は枠がありません。</p>
                            ) : (
                                selected.slots.map((s) => <PanelSlot key={s.id} s={s} />)
                            )}
                        </aside>
                    </>
                )}
            </div>
        </AdminShell>
    );
}
