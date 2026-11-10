export type BadgeKind = 'paid' | 'onsite' | 'monthly' | 'full' | 'closed' | 'submitted' | 'unsubmitted';

const STYLE: Record<BadgeKind, { label: string; className: string }> = {
    paid: { label: '支払済', className: 'bg-paid-soft text-paid border-paid/40' },
    onsite: { label: '当日払い', className: 'bg-onsite-soft text-onsite border-onsite/40' },
    monthly: { label: '月謝', className: 'bg-brand text-white border-brand' },
    full: { label: '満席', className: 'bg-alert-soft text-alert border-alert/40' },
    closed: { label: 'お休み', className: 'bg-line text-ink-soft border-line' },
    submitted: { label: '提出済', className: 'bg-paid-soft text-paid border-paid/40' },
    unsubmitted: { label: '未提出', className: 'bg-alert-soft text-alert border-alert/40' },
};

/** 一覧や枠の状態を表すバッジ */
export default function StatusBadge({ kind }: { kind: BadgeKind }) {
    const s = STYLE[kind];
    return <span className={'inline-block rounded border px-1.5 py-0.5 text-[11px] leading-none font-bold whitespace-nowrap ' + s.className}>{s.label}</span>;
}
