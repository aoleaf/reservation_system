import type { SlotStatus } from '@/types/models';

type Props = {
    monthly: number;
    trial: number;
    capacity: number;
    /** 体験だけ受けられる空き（斜線で塗る） */
    trialOnlyFree?: number;
    status?: SlotStatus;
    size?: 'sm' | 'md';
};

/**
 * 1枠の内訳を1本の帯で表す。左から 月謝（濃）→ 体験（薄）→ 体験のみ可の空き（斜線）→ 空き（白）。
 * 色だけでなく、並び順と長さでも区別できる。受付停止は点線の枠、お休みはグレー。
 */
export default function SlotBar({ monthly, trial, capacity, trialOnlyFree = 0, status = 'open', size = 'sm' }: Props) {
    const h = size === 'sm' ? 'h-2' : 'h-3';
    if (status === 'closed') {
        return <span className={`bg-line block w-full rounded-sm ${h}`} aria-label="お休み" />;
    }
    const pct = (n: number) => `${(Math.max(0, n) / capacity) * 100}%`;
    return (
        <span
            className={
                `border-line flex w-full overflow-hidden rounded-sm border bg-white ${h} ` +
                (status === 'paused' ? 'outline-onsite outline-2 outline-offset-1 outline-dashed' : '')
            }
            aria-label={
                `月謝${monthly}・体験${trial}／${capacity}` +
                (trialOnlyFree ? `（体験のみ可 ${trialOnlyFree}）` : '') +
                (status === 'paused' ? '・受付停止' : '')
            }
        >
            <span className="bg-brand" style={{ width: pct(monthly) }} />
            <span className="bg-brand-soft" style={{ width: pct(trial) }} />
            <span className="bg-hatch" style={{ width: pct(trialOnlyFree) }} />
        </span>
    );
}
