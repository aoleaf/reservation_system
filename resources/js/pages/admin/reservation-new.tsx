import AdminTabs from '@/components/admin-tabs';
import MobileShell from '@/components/mobile-shell';
import SlotCard from '@/components/slot-card';
import { createStaffReservation } from '@/lib/actions';
import type { AdminReservationNewProps } from '@/types/pages';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

const input = 'border-line w-full rounded-md border bg-white px-3 py-2.5 text-base focus:border-brand focus:outline-none';

/** スタッフの予約入力（電話・DM）：種類 → 日付 → 枠 → お客様（体験）／生徒（月謝） */
export default function AdminReservationNew(props: AdminReservationNewProps) {
    const { kind, date, label, dateNote, dateOptions, slots, initialSlotId, channels, monthLabel, required, members, initialMemberId } = props;
    const [slotId, setSlotId] = useState<string | null>(initialSlotId);
    const [n, setN] = useState(1);
    const [name, setName] = useState('');
    const [phone, setPhone] = useState('');
    const [memberId, setMemberId] = useState(initialMemberId ?? '');
    const [channel, setChannel] = useState(channels[0].value);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const monthly = kind === 'monthly';
    const slot = slots.find((s) => s.id === slotId && s.bookable) ?? null;
    const member = members.find((m) => m.id === memberId) ?? null;
    const memberFull = member !== null && member.count >= required;
    const sameDay = member !== null && slot !== null && member.dates.includes(slot.date);

    let blocker: string | null = null;
    if (!slot) blocker = '枠を選んでください';
    else if (monthly && !member) blocker = '生徒さんを選んでください';
    else if (monthly && memberFull) blocker = `この月はもう${required}回入っています`;
    else if (monthly && sameDay) blocker = 'この生徒さんは同じ日にもう予約があります';
    else if (!monthly && !name.trim()) blocker = 'お名前を入力してください';

    const submit = () => {
        if (!slot) return;
        setBusy(true);
        setErrors({});
        const cb = { onError: setErrors, onFinish: () => setBusy(false) };
        if (monthly) createStaffReservation({ kind: 'monthly', slotId: slot.id, channel, memberId }, cb);
        else createStaffReservation({ kind: 'trial', slotId: slot.id, channel, partySize: n, contact: { name, phone } }, cb);
    };

    const kindTab = (k: 'trial' | 'monthly', text: string) => (
        <Link
            href={route('admin.reservations.new', k === 'monthly' ? { date, kind: k, ...(memberId ? { member: memberId } : {}) } : { date })}
            className={'flex-1 rounded-md py-2 text-center text-sm ' + (kind === k ? 'bg-brand font-bold text-white' : 'text-ink-soft bg-white')}
        >
            {text}
        </Link>
    );

    return (
        <MobileShell title="予約の入力" variant="staff" back={route('admin.today', { date })} footer={<AdminTabs current="today" />}>
            <div className="border-line mb-4 flex gap-1 rounded-lg border bg-white p-1">
                {kindTab('trial', '体験のお客様')}
                {kindTab('monthly', '月謝の生徒さん')}
            </div>

            <section className="mb-4">
                <h2 className="mb-2 text-sm font-bold">1. 日付</h2>
                <div className="-mx-4 flex gap-1.5 overflow-x-auto px-4 pb-1">
                    {dateOptions.map((d) => (
                        <Link
                            key={d.date}
                            href={route('admin.reservations.new', { date: d.date, ...(monthly ? { kind, ...(memberId ? { member: memberId } : {}) } : {}) })}
                            className={
                                'shrink-0 rounded-full border px-3 py-1.5 text-xs whitespace-nowrap ' +
                                (d.date === date ? 'border-brand bg-brand font-bold text-white' : 'border-line bg-white')
                            }
                        >
                            {d.label}
                        </Link>
                    ))}
                </div>
                <p className="mt-2 font-bold">{label}</p>
                {dateNote && <p className="text-ink-soft mt-1 text-sm">{dateNote}</p>}
            </section>

            <section className="mb-4">
                <h2 className="mb-2 text-sm font-bold">2. 枠{monthly && <span className="text-ink-soft ml-1 font-normal">（月謝の残席）</span>}</h2>
                <div className="space-y-2">
                    {slots.map((s) => (
                        <SlotCard
                            key={s.id}
                            slot={s}
                            selected={s.id === slotId}
                            onClick={() => {
                                setSlotId(s.id);
                                setN(1);
                            }}
                        />
                    ))}
                </div>
            </section>

            <section className="space-y-3">
                {monthly ? (
                    <>
                        <h2 className="text-sm font-bold">3. 生徒さん</h2>
                        <label className="block">
                            <span className="mb-1 block text-sm">
                                生徒さん（{monthLabel}の回数）
                            </span>
                            <select className={input} value={memberId} onChange={(e) => setMemberId(e.target.value)}>
                                <option value="">選んでください</option>
                                {members.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.id} {m.name}（{m.count}/{required}回）
                                    </option>
                                ))}
                            </select>
                        </label>
                        {member && (
                            <p className="text-ink-soft text-xs">
                                {monthLabel}：{member.count}/{required}回 入っています
                            </p>
                        )}
                    </>
                ) : (
                    <>
                        <h2 className="text-sm font-bold">3. お客様</h2>
                        <label className="block">
                            <span className="mb-1 block text-sm">人数</span>
                            <select className={input} value={n} onChange={(e) => setN(Number(e.target.value))} disabled={!slot}>
                                {Array.from({ length: Math.max(1, slot?.remaining ?? 1) }, (_, i) => i + 1).map((v) => (
                                    <option key={v} value={v}>
                                        {v}名
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-sm">お名前（必須）</span>
                            <input className={input} value={name} onChange={(e) => setName(e.target.value)} />
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-sm">電話番号</span>
                            <input className={input} value={phone} onChange={(e) => setPhone(e.target.value)} type="tel" />
                        </label>
                    </>
                )}
                <fieldset>
                    <legend className="mb-1 text-sm">経路</legend>
                    <div className="flex gap-2">
                        {channels.map((c) => (
                            <label
                                key={c.value}
                                className={'flex-1 rounded-md border py-2 text-center text-sm ' + (channel === c.value ? 'border-brand bg-brand-faint text-brand font-bold' : 'border-line bg-white')}
                            >
                                <input type="radio" name="channel" value={c.value} checked={channel === c.value} onChange={() => setChannel(c.value)} className="sr-only" />
                                {c.label}
                            </label>
                        ))}
                    </div>
                </fieldset>
            </section>

            {Object.values(errors).map((e) => (
                <p key={e} role="alert" className="text-alert mt-3 text-sm font-bold">
                    {e}
                </p>
            ))}

            <button type="button" onClick={submit} disabled={blocker !== null || busy} className="bg-brand disabled:bg-line disabled:text-ink-soft mt-5 block w-full rounded-lg py-3 font-bold text-white">
                {blocker ?? (monthly ? '月謝の予約を登録する' : '登録する（当日払い）')}
            </button>
        </MobileShell>
    );
}
