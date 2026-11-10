// デモ用の上の帯。ストアを直接触ってよい唯一のUI（本番では mocks/ ごと消す）。
import { router } from '@inertiajs/react';
import { shortDayLabel } from './dates';
import type { Role } from './seed';
import { useMockStore } from './store';

const ROLES: { role: Role; label: string; to: string }[] = [
    { role: 'customer', label: 'お客様', to: 'reserve.index' },
    { role: 'member', label: '生徒さん', to: 'member.index' },
    { role: 'staff', label: 'スタッフ', to: 'admin.today' },
];

// 体験日 11/22 のデモ予約（cancelToken 'demo'）が基準
const DAYS = [
    { date: '2026-11-20', label: '11/20' },
    { date: '2026-11-19', label: '3日前' },
    { date: '2026-11-21', label: '前日' },
    { date: '2026-11-22', label: '当日' },
];

export default function DemoBar() {
    const role = useMockStore((s) => s.role);
    const today = useMockStore((s) => s.today);
    const setRole = useMockStore((s) => s.setRole);
    const setToday = useMockStore((s) => s.setToday);
    const reset = useMockStore((s) => s.reset);

    const chip = (active: boolean) => 'rounded px-1.5 py-0.5 ' + (active ? 'bg-white text-ink font-bold' : 'bg-white/15');

    return (
        <div className="bg-ink mx-auto w-full max-w-[420px] space-y-1 px-2 py-1.5 text-[11px] text-white">
            <div className="flex items-center gap-1.5">
                <span className="font-bold">確認用の画面です</span>
                <span className="opacity-70">今日 {shortDayLabel(today)}</span>
                <button
                    type="button"
                    className="ml-auto rounded bg-white/15 px-1.5 py-0.5"
                    onClick={() => {
                        if (confirm('データを最初の状態に戻します。よろしいですか？')) reset();
                    }}
                >
                    リセット
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
                <span className="mx-0.5 opacity-40">|</span>
                {DAYS.map((d) => (
                    <button key={d.date} type="button" className={chip(today === d.date)} onClick={() => setToday(d.date)}>
                        {d.label}
                    </button>
                ))}
            </div>
        </div>
    );
}
