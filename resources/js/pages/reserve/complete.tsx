import MobileShell from '@/components/mobile-shell';
import NotFound from '@/components/not-found';
import { yen } from '@/lib/format';
import type { ReserveCompleteProps } from '@/types/pages';
import { Link } from '@inertiajs/react';

/** 予約の完了。予約番号とキャンセル用のリンク */
export default function ReserveComplete({ reservation, slotLabel, cancelPolicy }: ReserveCompleteProps) {
    if (!reservation) {
        return (
            <MobileShell title="予約の確認">
                <NotFound message="予約が見つかりません" backHref={route('reserve.index')} backLabel="枠の一覧へ戻る" />
            </MobileShell>
        );
    }
    return (
        <MobileShell title="予約が完了しました">
            <div className="bg-brand-faint mb-5 rounded-lg p-4 text-center">
                <p className="text-sm">予約番号</p>
                <p className="text-brand text-2xl font-bold tracking-wider">{reservation.id}</p>
                <p className="text-ink-soft mt-1 text-xs">当日、受付でお伝えください</p>
            </div>
            <dl className="border-line divide-line divide-y rounded-lg border bg-white text-sm">
                {[
                    ['日時', slotLabel],
                    ['人数', `${reservation.partySize}名`],
                    ['お名前', reservation.contact.name],
                    ['お支払い', `${yen(reservation.amount)}（支払済）`],
                ].map(([k, v]) => (
                    <div key={k} className="flex gap-3 px-3 py-2.5">
                        <dt className="text-ink-soft w-20 shrink-0">{k}</dt>
                        <dd>{v}</dd>
                    </div>
                ))}
            </dl>
            <section className="mt-5 text-sm">
                <h2 className="mb-2 font-bold">キャンセルするときは</h2>
                <ul className="text-ink-soft mb-2 list-disc space-y-1 pl-5">
                    {cancelPolicy.map((p) => (
                        <li key={p}>{p}</li>
                    ))}
                </ul>
                {reservation.cancelToken && (
                    <Link href={route('reserve.cancel', reservation.cancelToken)} className="text-brand underline">
                        キャンセル用のページ
                    </Link>
                )}
            </section>
            <Link href={route('reserve.index')} className="border-brand text-brand mt-6 block rounded-lg border py-3 text-center font-bold">
                枠の一覧へ戻る
            </Link>
        </MobileShell>
    );
}
