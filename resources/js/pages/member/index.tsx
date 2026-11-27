import MobileShell from '@/components/mobile-shell';
import NotFound from '@/components/not-found';
import StatusBadge from '@/components/status-badge';
import type { MemberIndexProps } from '@/types/pages';
import { Link } from '@inertiajs/react';

/** 生徒のマイページ：今月・来月の回と日程変更の残り回数 */
export default function MemberIndex({ member, months, sessionsPerMonth }: MemberIndexProps) {
    if (!member) {
        return (
            <MobileShell title="マイページ" back={route('demo')}>
                <NotFound message="生徒さんの情報が見つかりません" backHref={route('demo')} backLabel="最初の画面へ" />
            </MobileShell>
        );
    }
    return (
        <MobileShell title="マイページ" back={route('demo')}>
            <p className="mb-4">
                <span className="font-mincho text-lg font-bold">{member.name}</span> さん
            </p>
            <div className="space-y-5">
                {months.map((m) => (
                    <section key={m.month} className="border-line rounded-lg border bg-white p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <h2 className="font-bold">{m.label}</h2>
                            <StatusBadge kind={m.submitted ? 'submitted' : 'unsubmitted'} />
                            {m.submitted && <span className="text-ink-soft ml-auto text-xs">日程変更 残り{m.changeRemaining}回</span>}
                        </div>
                        {m.sessions.length > 0 && (
                            <ol className="divide-line divide-y">
                                {m.sessions.map((x, i) => (
                                    <li key={x.reservationId} className={'flex items-center gap-2 py-2 text-sm ' + (x.past ? 'text-ink-soft' : '')}>
                                        <span className="text-ink-soft w-10 shrink-0">{i + 1}回目</span>
                                        <span className="min-w-0 flex-1">{x.label}</span>
                                        {x.past ? (
                                            <span className="text-xs">済</span>
                                        ) : x.canChange ? (
                                            <Link href={route('member.change', x.reservationId)} className="border-brand text-brand shrink-0 rounded-md border px-2 py-1 text-xs font-bold">
                                                変更
                                            </Link>
                                        ) : (
                                            <button type="button" disabled className="border-line text-ink-soft/60 shrink-0 rounded-md border px-2 py-1 text-xs">
                                                変更
                                            </button>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        )}
                        {!m.submitted &&
                            (m.canSchedule ? (
                                <Link href={route('member.schedule', m.month)} className="bg-brand block rounded-lg py-3 text-center font-bold text-white">
                                    来月の日程を選ぶ（{sessionsPerMonth}回）
                                </Link>
                            ) : (
                                <p className="text-ink-soft text-sm">まだ日程は選べません。</p>
                            ))}
                    </section>
                ))}
            </div>
        </MobileShell>
    );
}
