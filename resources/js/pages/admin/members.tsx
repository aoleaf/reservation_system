import AdminTabs from '@/components/admin-tabs';
import MobileShell from '@/components/mobile-shell';
import StatusBadge from '@/components/status-badge';
import type { AdminMembersProps } from '@/types/pages';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

/** 生徒一覧。来月の提出済み／未提出と、「未提出だけ」の絞り込み */
export default function AdminMembers({ monthLabel, filter, counts, members }: AdminMembersProps) {
    const tab = (key: 'all' | 'unsubmitted', text: string) => (
        <Link
            href={route('admin.members', key === 'all' ? {} : { filter: key })}
            className={'flex-1 rounded-md py-2 text-center text-sm ' + (filter === key ? 'bg-brand font-bold text-white' : 'text-ink-soft bg-white')}
        >
            {text}
        </Link>
    );
    return (
        <MobileShell title="生徒一覧" variant="staff" footer={<AdminTabs current="members" />}>
            <p className="mb-2 text-sm">
                {monthLabel}の日程：未提出 <span className="text-alert font-bold">{counts.unsubmitted}名</span>／{counts.all}名
            </p>
            <div className="border-line mb-3 flex gap-1 rounded-lg border bg-white p-1">
                {tab('all', `全員（${counts.all}）`)}
                {tab('unsubmitted', `未提出だけ（${counts.unsubmitted}）`)}
            </div>
            <ul className="border-line divide-line divide-y rounded-lg border bg-white">
                {members.map((m) => (
                    <li key={m.id}>
                        <Link href={route('admin.members.show', m.id)} className="flex items-center gap-2 px-3 py-2.5 text-sm">
                            <span className="text-ink-soft w-8 text-xs tabular-nums">{m.id}</span>
                            <span className="min-w-0 flex-1 truncate">{m.name}</span>
                            <StatusBadge kind={m.submitted ? 'submitted' : 'unsubmitted'} />
                            <ChevronRight className="text-ink-soft h-4 w-4" />
                        </Link>
                    </li>
                ))}
            </ul>
            {members.length === 0 && <p className="text-ink-soft mt-3 text-sm">該当する生徒さんはいません。</p>}
        </MobileShell>
    );
}
