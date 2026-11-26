// 確認用の帯（本番では mocks/ ごと消す）。今はすべての画面が BE（開発用の DB）を見るので、立場の切替だけを残す。
// ふだんは右下の小さなボタンだけを出し、押すと画面の上に帯を開く。
import { router } from '@inertiajs/react';
import { SlidersHorizontal, X } from 'lucide-react';
import type { Role } from './seed';
import { useMockStore } from './store';

const ROLES: { role: Role; label: string; to: string }[] = [
    { role: 'customer', label: 'お客様', to: 'reserve.index' },
    { role: 'member', label: '生徒さん', to: 'member.index' },
    { role: 'staff', label: 'スタッフ', to: 'admin.today' },
];

/** いま開いているページから立場を決める */
const roleOfPage = (page: string): Role | null =>
    page.startsWith('admin/') ? 'staff' : page.startsWith('member/') ? 'member' : page.startsWith('reserve/') ? 'customer' : null;

// 420px の列の右端にそろえる
const RIGHT = 'right-[max(0.75rem,calc(50%-210px+0.75rem))]';

export default function DemoBar({ page }: { page: string }) {
    const role = roleOfPage(page);
    // スタッフ画面は PC 幅なので、ボタンとメニューを画面の右端に置く
    const wide = page.startsWith('admin/');
    const open = useMockStore((s) => s.demoOpen);
    const setOpen = useMockStore((s) => s.setDemoOpen);

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
            <div
                className={'bg-ink mx-auto w-full max-w-[420px] space-y-1.5 px-3 py-2 text-xs text-white shadow-lg ' + (wide ? 'md:rounded-lg' : '')}
            >
                <div className="flex items-center gap-2">
                    <span className="font-bold">確認用メニュー</span>
                    <span className="opacity-70">データは開発用の DB・今日は実際の日付</span>
                    <button
                        type="button"
                        onClick={() => setOpen(false)}
                        aria-label="確認用のメニューを閉じる"
                        className="ml-auto rounded bg-white/15 p-1"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
                <div className="flex flex-wrap items-center gap-1">
                    {ROLES.map((r) => (
                        <button key={r.role} type="button" className={chip(role === r.role)} onClick={() => router.visit(route(r.to))}>
                            {r.label}
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}
