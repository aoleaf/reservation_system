import { resolveRefund } from '@/lib/actions';
import { yen } from '@/lib/format';
import type { RefundIssue } from '@/types/pages';
import { AlertTriangle } from 'lucide-react';

/** 返金に失敗した予約の一覧。手で返金したら「返金した」を押して消す */
export default function RefundIssues({ issues }: { issues: RefundIssue[] }) {
    if (issues.length === 0) return null;
    return (
        <section role="alert" className="border-alert/40 bg-alert-soft mb-5 rounded-lg border p-3 text-sm">
            <h2 className="text-alert mb-1 flex items-center gap-1 font-bold">
                <AlertTriangle className="h-4 w-4" />
                返金できなかった予約があります（{issues.length}件）
            </h2>
            <p className="text-ink-soft mb-2 text-xs">Stripe のダッシュボードで返金してから、「返金した」を押してください。</p>
            <ul className="divide-alert/20 divide-y">
                {issues.map((r) => (
                    <li key={r.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 py-1.5">
                        <span className="font-bold">{r.name}</span>
                        <span className="text-ink-soft text-xs">
                            {r.code}・{r.slotLabel}
                            {r.phone && `・${r.phone}`}
                        </span>
                        <span className="font-bold">返金 {yen(r.amount)}</span>
                        <button
                            type="button"
                            onClick={() => resolveRefund({ reservationId: r.id })}
                            className="ml-auto rounded border bg-white px-2 py-1 text-xs"
                        >
                            返金した
                        </button>
                    </li>
                ))}
            </ul>
        </section>
    );
}
