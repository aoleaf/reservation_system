import { Link } from '@inertiajs/react';
import { CalendarDays, ClipboardList, Plus, Settings, Users } from 'lucide-react';

export type AdminTab = 'today' | 'slots' | 'members' | 'settings' | 'new';

type Props = { current: AdminTab; variant?: 'bottom' | 'top' };

const TABS = [
    { key: 'today', label: '今日の予約', short: '今日', to: 'admin.today', Icon: ClipboardList },
    { key: 'slots', label: 'カレンダー', short: 'カレンダー', to: 'admin.slots', Icon: CalendarDays },
    { key: 'members', label: '生徒', short: '生徒', to: 'admin.members', Icon: Users },
    { key: 'settings', label: '設定', short: '設定', to: 'admin.settings', Icon: Settings },
] as const;

/** スタッフ画面のナビ。PC はヘッダーの中（top）、スマホは画面の下（bottom） */
export default function AdminTabs({ current, variant = 'bottom' }: Props) {
    if (variant === 'top') {
        return (
            <nav className="flex items-center gap-1">
                {TABS.map(({ key, label, to, Icon }) => (
                    <Link
                        key={key}
                        href={route(to)}
                        className={
                            'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm ' +
                            (current === key ? 'bg-white/15 font-bold text-white' : 'text-white/70 hover:text-white')
                        }
                    >
                        <Icon className="h-4 w-4" />
                        {label}
                    </Link>
                ))}
                <Link
                    href={route('admin.reservations.new')}
                    className={
                        'ml-2 flex items-center gap-1 rounded-md px-3 py-1.5 text-sm font-bold ' +
                        (current === 'new' ? 'text-ink bg-white' : 'bg-brand text-white')
                    }
                >
                    <Plus className="h-4 w-4" />
                    予約を入れる
                </Link>
            </nav>
        );
    }
    return (
        <nav className="border-line grid grid-cols-4 border-t bg-white">
            {TABS.map(({ key, short, to, Icon }) => (
                <Link
                    key={key}
                    href={route(to)}
                    className={'flex flex-col items-center gap-0.5 py-2 text-xs ' + (current === key ? 'text-brand font-bold' : 'text-ink-soft')}
                >
                    <Icon className="h-5 w-5" />
                    {short}
                </Link>
            ))}
        </nav>
    );
}
