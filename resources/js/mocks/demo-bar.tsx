// デモ用の帯。ストアを直接触ってよい唯一のUI（本番では mocks/ ごと消す）。
// ふだんは右下の小さなボタンだけを出し、押すと画面の上に帯を開く。
import { router } from '@inertiajs/react';
import { SlidersHorizontal, X } from 'lucide-react';
import { shortDayLabel } from './dates';
import type { Role } from './seed';
import { useMockStore } from './store';

const ROLES: { role: Role; label: string; to: string }[] = [
    { role: 'customer', label: 'お客様', to: 'reserve.index' },
    { role: 'member', label: '生徒さん', to: 'member.index' },
    { role: 'staff', label: 'スタッフ', to: 'admin.today' },
];

// 11/12 は初期値。締め後（11/16）は12月の日程の受付が終わった日。3日前・前日・当日 は体験日 11/22 のデモ予約（cancelToken 'demo'）が基準
const DAYS = [
    { date: '2026-11-12', label: '11/12' },
    { date: '2026-11-16', label: '締め後' },
    { date: '2026-11-19', label: '3日前' },
    { date: '2026-11-21', label: '前日' },
    { date: '2026-11-22', label: '当日' },
];

/** いま開いているページから立場を決める（入口のリンクから来たときもずれないように） */
const roleOfPage = (page: string): Role | null => (page.startsWith('admin/') ? 'staff' : page.startsWith('member/') ? 'member' : page.startsWith('reserve/') ? 'customer' : null);

// 420px の列の右端にそろえる
const RIGHT = 'right-[max(0.75rem,calc(50%-210px+0.75rem))]';

export default function DemoBar({ page }: { page: string }) {
    const storedRole = useMockStore((s) => s.role);
    const role = roleOfPage(page) ?? storedRole;
    // スタッフ画面は PC 幅なので、ボタンとメニューを画面の右端に置く
    const wide = page.startsWith('admin/');
    const today = useMockStore((s) => s.today);
    const open = useMockStore((s) => s.demoOpen);
    const setOpen = useMockStore((s) => s.setDemoOpen);
    const setRole = useMockStore((s) => s.setRole);
    const setToday = useMockStore((s) => s.setToday);
    const reset = useMockStore((s) => s.reset);
    // 本番では予約確認メールに入るキャンセル用リンク。モックではここから開く
    const latestToken = useMockStore((s) => [...s.reservations].reverse().find((r) => r.channel === 'web' && r.cancelToken && r.cancelToken !== 'demo')?.cancelToken);

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                aria-label="確認用のメニューを開く"
                className={`bg-ink/60 fixed bottom-20 z-50 flex h-9 w-9 items-center justify-center rounded-full text-white shadow ${wide ? 'right-4 md:bottom-6' : RIGHT}`}
            >
                <SlidersHorizontal className="h-4 w-4" />
            </button>
        );
    }

    const chip = (active: boolean) => 'rounded px-2 py-1 ' + (active ? 'bg-white text-ink font-bold' : 'bg-white/15');

    return (
        <div className={wide ? 'fixed top-0 right-0 z-50 w-full max-w-[420px] md:top-16 md:right-4' : 'fixed inset-x-0 top-0 z-50'}>
            <div className={'bg-ink mx-auto w-full max-w-[420px] space-y-1.5 px-3 py-2 text-xs text-white shadow-lg ' + (wide ? 'md:rounded-lg' : '')}>
                <div className="flex items-center gap-2">
                    <span className="font-bold">確認用メニュー</span>
                    <span className="opacity-70">今日 {shortDayLabel(today)}</span>
                    <button
                        type="button"
                        className="ml-auto rounded bg-white/15 px-2 py-1"
                        onClick={() => {
                            if (confirm('データを最初の状態に戻します。よろしいですか？')) reset();
                        }}
                    >
                        リセット
                    </button>
                    <button type="button" onClick={() => setOpen(false)} aria-label="確認用のメニューを閉じる" className="rounded bg-white/15 p-1">
                        <X className="h-4 w-4" />
                    </button>
                </div>
                <div className="flex flex-wrap items-center gap-1">
                    {ROLES.map((r) => (
                        <button
                            key={r.role}
                            type="button"
                            className={chip(role === r.role)}
                            onClick={() => {
                                setRole(r.role);
                                router.visit(route(r.to));
                            }}
                        >
                            {r.label}
                        </button>
                    ))}
                </div>
                <div className="flex flex-wrap items-center gap-1">
                    <span className="mr-1 opacity-70">メールのリンク</span>
                    <button type="button" className={chip(false)} onClick={() => router.visit(route('reserve.cancel', 'demo'))}>
                        デモ予約（11/22）
                    </button>
                    {latestToken && (
                        <button type="button" className={chip(false)} onClick={() => router.visit(route('reserve.cancel', latestToken))}>
                            最新のWeb予約
                        </button>
                    )}
                </div>
                <div className="flex flex-wrap items-center gap-1">
                    <span className="mr-1 opacity-70">今日の日付</span>
                    {DAYS.map((d) => (
                        <button key={d.date} type="button" className={chip(today === d.date)} onClick={() => setToday(d.date)}>
                            {d.label}
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}
