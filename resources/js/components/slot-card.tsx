import type { SlotView } from '@/types/pages';
import { Link } from '@inertiajs/react';
import SeatMeter from './seat-meter';
import StatusBadge from './status-badge';

type Props = {
    slot: SlotView;
    /** 押したときの遷移先。onClick と併用しない */
    href?: string;
    onClick?: () => void;
    selected?: boolean;
    /** 残席の書き方（体験なら「残り3席」） */
    showRemaining?: boolean;
};

/** 枠を選ぶ画面のカード。bookable=false ならグレーで押せない */
export default function SlotCard({ slot, href, onClick, selected = false, showRemaining = true }: Props) {
    const body = (
        <div className="flex items-center gap-3">
            <div className="w-24 shrink-0">
                <div className="text-base font-bold tabular-nums">{slot.start}</div>
                <div className="text-ink-soft text-xs tabular-nums">〜{slot.end}</div>
            </div>
            <div className="min-w-0 flex-1">
                <SeatMeter monthly={slot.used.monthly} trial={slot.used.trial} capacity={slot.capacity} />
            </div>
            <div className="shrink-0 text-right text-sm">
                {slot.blocked ? (
                    <StatusBadge kind="closed" />
                ) : !slot.bookable ? (
                    <StatusBadge kind="full" />
                ) : showRemaining ? (
                    <span className="text-brand font-bold">残り{slot.remaining}席</span>
                ) : null}
            </div>
        </div>
    );
    const base = 'block w-full rounded-lg border p-3 text-left transition ';
    if (!slot.bookable) {
        return (
            <div className={base + 'border-line bg-line/40 cursor-not-allowed opacity-60'} aria-disabled="true">
                {body}
            </div>
        );
    }
    const cls = base + (selected ? 'border-brand bg-brand-faint ring-brand ring-2' : 'border-line bg-white active:bg-brand-faint');
    if (href) {
        return (
            <Link href={href} className={cls}>
                {body}
            </Link>
        );
    }
    return (
        <button type="button" onClick={onClick} className={cls} aria-pressed={selected}>
            {body}
        </button>
    );
}
