import MobileShell from '@/components/mobile-shell';
import { Link } from '@inertiajs/react';

/** 3つの立場への入口 */
export default function Demo() {
    const entries = [
        { href: route('reserve.index'), title: 'お客様', body: '体験レッスンの枠を選んで予約する' },
        { href: route('member.index'), title: '生徒さん', body: '月謝の日程を見る・来月を選ぶ・日程を変える' },
        { href: route('admin.today'), title: 'スタッフ', body: '今日の予約、電話予約の入力、カレンダー、生徒一覧' },
    ];
    return (
        <MobileShell title="つちのね 予約システム">
            <div className="border-brand/30 bg-brand-faint mb-6 rounded-lg border p-4 text-sm leading-relaxed">
                <p className="font-bold">確認用の画面です</p>
                <p className="mt-1">
                    データはこのブラウザの中だけで動きます。実際の予約や支払いは行われません。上の帯で「今日の日付」を変えたり、データを最初の状態に戻したりできます。
                </p>
            </div>
            <ul className="space-y-3">
                {entries.map((e) => (
                    <li key={e.title}>
                        <Link href={e.href} className="border-line block rounded-lg border bg-white p-4 active:bg-brand-faint">
                            <span className="font-mincho text-brand text-lg font-bold">{e.title}</span>
                            <span className="text-ink-soft mt-1 block text-sm">{e.body}</span>
                        </Link>
                    </li>
                ))}
            </ul>
        </MobileShell>
    );
}
