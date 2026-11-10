import AdminTabs from '@/components/admin-tabs';
import MobileShell from '@/components/mobile-shell';
import NotFound from '@/components/not-found';
import StatusBadge from '@/components/status-badge';
import type { AdminMemberShowProps } from '@/types/pages';

/** 生徒の詳細（代理入力は次の段階） */
export default function AdminMemberShow({ member, months }: AdminMemberShowProps) {
    return (
        <MobileShell title="生徒の詳細" variant="staff" back={route('admin.members')} footer={<AdminTabs current="members" />}>
            {!member ? (
                <NotFound message="生徒さんが見つかりません" backHref={route('admin.members')} backLabel="生徒一覧へ戻る" />
            ) : (
                <>
                    <section className="border-line mb-4 rounded-lg border bg-white p-3 text-sm">
                        <p className="text-lg font-bold">{member.name}</p>
                        <p className="text-ink-soft mt-1">
                            {member.id}・{member.phone}・{member.email}
                        </p>
                    </section>
                    {months.map((m) => (
                        <section key={m.month} className="border-line mb-3 rounded-lg border bg-white p-3 text-sm">
                            <div className="mb-2 flex items-center gap-2">
                                <h2 className="font-bold">{m.label}</h2>
                                <StatusBadge kind={m.submitted ? 'submitted' : 'unsubmitted'} />
                                {m.submitted && <span className="text-ink-soft ml-auto text-xs">日程変更 残り{m.changeRemaining}回</span>}
                            </div>
                            {m.sessions.length === 0 ? (
                                <p className="text-ink-soft">日程はまだありません</p>
                            ) : (
                                <ol className="list-decimal space-y-0.5 pl-5">
                                    {m.sessions.map((x) => (
                                        <li key={x}>{x}</li>
                                    ))}
                                </ol>
                            )}
                        </section>
                    ))}
                    <p className="text-ink-soft text-xs">代理入力（スタッフが日程を選ぶ・変える）は次の段階で作ります。</p>
                </>
            )}
        </MobileShell>
    );
}
