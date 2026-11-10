import type { PickDay, SlotView } from '@/types/pages';
import MonthCalendar from './month-calendar';
import SlotCard from './slot-card';

type Props = {
    days: PickDay[];
    leadingBlanks: number;
    activeDate: string;
    onSelectDate: (date: string) => void;
    selectedIds: string[];
    onToggleSlot: (slot: SlotView) => void;
};

/** カレンダーで日を選び、その日の枠を選ぶ（生徒の「来月を選ぶ」「日程変更」） */
export default function DaySlotPicker({ days, leadingBlanks, activeDate, onSelectDate, selectedIds, onToggleSlot }: Props) {
    const active = days.find((d) => d.date === activeDate);
    const chosenDates = new Set(days.filter((d) => d.slots.some((s) => selectedIds.includes(s.id))).map((d) => d.date));
    return (
        <div>
            <MonthCalendar
                leadingBlanks={leadingBlanks}
                days={days}
                renderDay={(d) => {
                    const chosen = chosenDates.has(d.date);
                    return (
                        <button
                            type="button"
                            disabled={!d.selectable && !chosen}
                            onClick={() => onSelectDate(d.date)}
                            aria-label={d.label}
                            aria-pressed={d.date === activeDate}
                            className={
                                'relative flex h-11 w-full flex-col items-center justify-center rounded-md border text-sm tabular-nums ' +
                                (chosen
                                    ? 'border-brand bg-brand font-bold text-white '
                                    : d.selectable
                                      ? 'border-line bg-white '
                                      : 'bg-line/50 text-ink-soft/60 border-transparent ') +
                                (d.date === activeDate ? 'ring-brand ring-2 ring-offset-1' : '')
                            }
                        >
                            {d.day}
                            {d.closed && <span className="text-[9px] leading-none">休</span>}
                        </button>
                    );
                }}
            />
            <div className="mt-4">
                {!active ? (
                    <p className="text-ink-soft text-sm">カレンダーから日を選んでください。</p>
                ) : (
                    <section>
                        <h3 className="mb-2 font-bold">{active.label}</h3>
                        <div className="space-y-2">
                            {active.slots.map((s) => (
                                <SlotCard key={s.id} slot={s} selected={selectedIds.includes(s.id)} onClick={() => onToggleSlot(s)} showRemaining={false} />
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </div>
    );
}
