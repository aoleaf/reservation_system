import AdminTabs from '@/components/admin-tabs';
import MobileShell from '@/components/mobile-shell';
import SlotCard from '@/components/slot-card';
import { createStaffReservation } from '@/lib/actions';
import type { AdminReservationNewProps } from '@/types/pages';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

const input = 'border-line w-full rounded-md border bg-white px-3 py-2.5 text-base focus:border-brand focus:outline-none';

/** スタッフの予約入力（電話・DM）：日付 → 枠 → 人数・名前・電話・経路 */
export default function AdminReservationNew({ date, label, dateNote, dateOptions, slots, initialSlotId, channels }: AdminReservationNewProps) {
    const [slotId, setSlotId] = useState<string | null>(initialSlotId);
    const [n, setN] = useState(1);
    const [name, setName] = useState('');
    const [phone, setPhone] = useState('');
    const [channel, setChannel] = useState(channels[0].value);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const slot = slots.find((s) => s.id === slotId && s.bookable) ?? null;
    const ready = slot !== null && name.trim() !== '';

    const submit = () => {
        if (!slot) return;
        setBusy(true);
        setErrors({});
        createStaffReservation({ slotId: slot.id, partySize: n, contact: { name, phone }, channel }, { onError: setErrors, onFinish: () => setBusy(false) });
    };

    return (
        <MobileShell title="予約の入力" variant="staff" back={route('admin.today', { date })} footer={<AdminTabs current="today" />}>
            <section className="mb-4">
                <h2 className="mb-2 text-sm font-bold">1. 日付</h2>
                <div className="-mx-4 flex gap-1.5 overflow-x-auto px-4 pb-1">
                    {dateOptions.map((d) => (
                        <Link
                            key={d.date}
                            href={route('admin.reservations.new', { date: d.date })}
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
                <h2 className="mb-2 text-sm font-bold">2. 枠</h2>
                <div className="space-y-2">
                    {slots.map((s) => (
                        <SlotCard key={s.id} slot={s} selected={s.id === slotId} onClick={() => {
                                setSlotId(s.id);
                                setN(1);
                            }}
                        />
                    ))}
                </div>
            </section>

            <section className="space-y-3">
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

            <button type="button" onClick={submit} disabled={!ready || busy} className="bg-brand disabled:bg-line disabled:text-ink-soft mt-5 block w-full rounded-lg py-3 font-bold text-white">
                {!slot ? '枠を選んでください' : !name.trim() ? 'お名前を入力してください' : '登録する（当日払い）'}
            </button>
        </MobileShell>
    );
}
