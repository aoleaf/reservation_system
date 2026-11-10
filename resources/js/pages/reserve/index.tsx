import MobileShell from '@/components/mobile-shell';
import SlotCard from '@/components/slot-card';
import { yen } from '@/lib/format';
import type { ReserveIndexProps } from '@/types/pages';

/** 体験レッスンの枠を選ぶ（土日だけ） */
export default function ReserveIndex({ days, unitPrice, note }: ReserveIndexProps) {
    return (
        <MobileShell title="体験レッスンの予約" back={route('demo')}>
            <div className="mb-4 text-sm leading-relaxed">
                <p>
                    体験レッスン 1名 <span className="font-bold">{yen(unitPrice)}</span>（2時間）
                </p>
                <p className="text-ink-soft mt-1">{note}</p>
            </div>
            {days.length === 0 && <p className="text-ink-soft">いま予約できる日はありません。</p>}
            <div className="space-y-5">
                {days.map((d) => (
                    <section key={d.date}>
                        <h2 className="font-mincho mb-2 font-bold">{d.label}</h2>
                        <div className="space-y-2">
                            {d.slots.map((s) => (
                                <SlotCard key={s.id} slot={s} href={route('reserve.form', { slot: s.id })} />
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </MobileShell>
    );
}
