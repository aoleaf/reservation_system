import DaySlotPicker from '@/components/day-slot-picker';
import MobileShell from '@/components/mobile-shell';
import NotFound from '@/components/not-found';
import { changeMonthlySession } from '@/lib/actions';
import type { MemberChangeProps, SlotView } from '@/types/pages';
import { useState } from 'react';

/** 日程変更：同じ月の空き枠へ移す（月1回まで） */
export default function MemberChange({ reservation, monthLabel, changeRemaining, blockedReason, leadingBlanks, days }: MemberChangeProps) {
    const [target, setTarget] = useState<SlotView | null>(null);
    const [activeDate, setActiveDate] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    if (!reservation) {
        return (
            <MobileShell title="日程の変更" back={route('member.index')}>
                <NotFound message="予約が見つかりません" backHref={route('member.index')} backLabel="マイページへ戻る" />
            </MobileShell>
        );
    }

    const submit = () => {
        if (!target) return;
        setBusy(true);
        setErrors({});
        changeMonthlySession({ reservationId: reservation.id, toSlotId: target.id }, { onError: setErrors, onFinish: () => setBusy(false) });
    };

    return (
        <MobileShell title="日程の変更" back={route('member.index')}>
            <div className="border-line mb-4 rounded-lg border bg-white p-3 text-sm">
                <p className="text-ink-soft">変更するレッスン</p>
                <p className="font-bold">{reservation.label}</p>
                <p className="text-ink-soft mt-1 text-xs">
                    {monthLabel}の日程変更 残り{changeRemaining}回
                </p>
            </div>

            {blockedReason ? (
                <p role="alert" className="bg-alert-soft text-alert rounded-lg p-3 text-sm font-bold">
                    {blockedReason}
                </p>
            ) : (
                <>
                    <p className="mb-3 text-sm">移したい日と時間を選んでください。</p>
                    <DaySlotPicker
                        days={days}
                        leadingBlanks={leadingBlanks}
                        activeDate={activeDate}
                        onSelectDate={setActiveDate}
                        selectedIds={target ? [target.id] : []}
                        onToggleSlot={(s) => setTarget(target?.id === s.id ? null : s)}
                    />
                    {Object.values(errors).map((e) => (
                        <p key={e} role="alert" className="text-alert mt-3 text-sm font-bold">
                            {e}
                        </p>
                    ))}
                    <button type="button" onClick={submit} disabled={!target || busy} className="bg-brand disabled:bg-line disabled:text-ink-soft mt-5 block w-full rounded-lg py-3 font-bold text-white">
                        {target ? `${Number(target.date.slice(5, 7))}/${Number(target.date.slice(8, 10))}（${target.weekday}） ${target.start} に変更する` : '移す枠を選んでください'}
                    </button>
                    <p className="text-ink-soft mt-2 text-center text-xs">変更すると、この月の日程変更は残り0回になります</p>
                </>
            )}
        </MobileShell>
    );
}
