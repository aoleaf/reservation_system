import MobileShell from '@/components/mobile-shell';
import { yen } from '@/lib/format';
import type { ReserveCompleteProps } from '@/types/pages';
import { Link, usePoll } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useEffect } from 'react';

/**
 * 決済から戻った画面。支払いの通知（Stripe）が届くまでは「確認中」にして、2秒ごとに読み直す。
 * 署名の無い URL では中身を出さない（予約番号だけで個人情報を見られないように）。
 */
export default function ReserveComplete({ reservation, paymentStatus, slotLabel, cancelPolicy, cancelGuide }: ReserveCompleteProps) {
    const { stop } = usePoll(2000, {}, { autoStart: paymentStatus === 'pending' });
    useEffect(() => {
        if (paymentStatus !== 'pending') stop();
    }, [paymentStatus, stop]);

    if (!reservation) {
        return (
            <MobileShell title="ご予約">
                <div className="border-line rounded-lg border bg-white p-5 text-center text-sm leading-relaxed">
                    <p>ご予約の内容は、予約確認メールをご覧ください。</p>
                    <Link href={route('reserve.index')} className="text-brand mt-3 inline-block underline">
                        予約のページへ
                    </Link>
                </div>
            </MobileShell>
        );
    }

    if (paymentStatus === 'pending') {
        return (
            <MobileShell title="お支払いを確認しています">
                <div className="flex flex-col items-center gap-3 py-10 text-center text-sm">
                    <LoaderCircle className="text-brand h-8 w-8 animate-spin" />
                    <p>お支払いを確認しています。このままお待ちください。</p>
                    <p className="text-ink-soft text-xs">予約番号 {reservation.id}</p>
                </div>
            </MobileShell>
        );
    }

    if (paymentStatus === 'expired') {
        return (
            <MobileShell title="ご予約をお取りできませんでした">
                <div className="bg-alert-soft text-alert mb-5 rounded-lg p-4 text-sm leading-relaxed">
                    <p className="font-bold">お支払いの期限が過ぎたため、席の仮押さえを解除しました。</p>
                    <p className="mt-1">お手数ですが、もう一度ご予約ください。お支払いが済んでいた場合は、全額を返金し、メールでお知らせします。</p>
                </div>
                <Link href={route('reserve.index')} className="bg-brand block rounded-lg py-3 text-center font-bold text-white">
                    予約のページへ
                </Link>
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
            {reservation.contact.email && (
                <p className="mb-4 text-sm">
                    予約確認メールを <span className="font-bold break-all">{reservation.contact.email}</span> にお送りしました。
                </p>
            )}
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
                <p className="mb-2">{cancelGuide}</p>
                <ul className="text-ink-soft list-disc space-y-1 pl-5">
                    {cancelPolicy.map((p) => (
                        <li key={p}>{p}</li>
                    ))}
                </ul>
            </section>
            <Link href={route('reserve.index')} className="border-brand text-brand mt-6 block rounded-lg border py-3 text-center font-bold">
                予約のページへ戻る
            </Link>
        </MobileShell>
    );
}
