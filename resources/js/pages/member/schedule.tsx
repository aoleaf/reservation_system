import DaySlotPicker from '@/components/day-slot-picker';
import MobileShell from '@/components/mobile-shell';
import NotFound from '@/components/not-found';
import { submitMonthlySchedule } from '@/lib/actions';
import type { MemberScheduleProps, SlotView } from '@/types/pages';
import { useState } from 'react';

/** 来月の日程を選ぶ。選択中の枠はこの画面の state で持ち、ストアに置かない */
export default function MemberSchedule({ month, monthLabel, unavailable, required, leadingBlanks, days }: MemberScheduleProps) {
    const [picked, setPicked] = useState<SlotView[]>([]);
    const [activeDate, setActiveDate] = useState(days.find((d) => d.selectable)?.date ?? '');
    const [notice, setNotice] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    if (unavailable) {
        return (
            <MobileShell title="来月の日程を選ぶ" back={route('member.index')}>
                <NotFound message={unavailable} backHref={route('member.index')} backLabel="マイページへ戻る" />
            </MobileShell>
        );
    }

    const toggle = (slot: SlotView) => {
        setNotice('');
        if (picked.some((p) => p.id === slot.id)) return setPicked(picked.filter((p) => p.id !== slot.id));
        const sameDay = picked.find((p) => p.date === slot.date);
        if (sameDay) {
            // 同じ日に2枠は選べないので、入れ替える
            setNotice('同じ日に2つの枠は選べないため、入れ替えました');
            return setPicked([...picked.filter((p) => p.id !== sameDay.id), slot]);
        }
        if (picked.length >= required) return setNotice(`選べるのは${required}つまでです。どれかを外してください`);
        setPicked([...picked, slot]);
    };
    const sorted = [...picked].sort((a, b) => (a.date + a.start).localeCompare(b.date + b.start));
    const ready = picked.length === required;

    const submit = () => {
        setBusy(true);
        setErrors({});
        submitMonthlySchedule({ month, slotIds: picked.map((p) => p.id) }, { onError: setErrors, onFinish: () => setBusy(false) });
    };

    return (
        <MobileShell title="来月の日程を選ぶ" back={route('member.index')}>
            <p className="mb-3 text-sm">
                {monthLabel}のレッスンを<span className="font-bold">{required}回</span>選んでください。グレーの日は選べません。
            </p>
            <DaySlotPicker
                days={days}
                leadingBlanks={leadingBlanks}
                activeDate={activeDate}
                onSelectDate={setActiveDate}
                selectedIds={picked.map((p) => p.id)}
                onToggleSlot={toggle}
            />
            {notice && <p className="text-onsite mt-3 text-sm font-bold">{notice}</p>}

            <section className="border-line mt-5 rounded-lg border bg-white p-3">
                <h3 className="mb-2 text-sm font-bold">
                    選んだ日程 {picked.length}／{required}
                </h3>
                {sorted.length === 0 && <p className="text-ink-soft text-sm">まだ選んでいません。</p>}
                <ul className="space-y-1 text-sm">
                    {sorted.map((p) => (
                        <li key={p.id} className="flex items-center gap-2">
                            <span className="tabular-nums">
                                {Number(p.date.slice(5, 7))}/{Number(p.date.slice(8, 10))}（{p.weekday}） {p.start}〜{p.end}
                            </span>
                            <button type="button" onClick={() => toggle(p)} className="text-ink-soft ml-auto text-xs underline">
                                外す
                            </button>
                        </li>
                    ))}
                </ul>
            </section>

            {Object.values(errors).map((e) => (
                <p key={e} role="alert" className="text-alert mt-3 text-sm font-bold">
                    {e}
                </p>
            ))}

            <button type="button" onClick={submit} disabled={!ready || busy} className="bg-brand disabled:bg-line disabled:text-ink-soft mt-5 block w-full rounded-lg py-3 font-bold text-white">
                {ready ? 'この日程で提出する' : `あと${required - picked.length}つ選んでください`}
            </button>
        </MobileShell>
    );
}
