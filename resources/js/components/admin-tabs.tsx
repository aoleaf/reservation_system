import { Link } from '@inertiajs/react';
import { CalendarDays, ClipboardList, Users } from 'lucide-react';

type Props = { current: 'today' | 'slots' | 'members' };

/** スタッフ画面の下のタブ */
export default function AdminTabs({ current }: Props) {
    const tabs = [
        { key: 'today', label: '今日', href: route('admin.today'), Icon: ClipboardList },
        { key: 'slots', label: 'カレンダー', href: route('admin.slots'), Icon: CalendarDays },
        { key: 'members', label: '生徒', href: route('admin.members'), Icon: Users },
    ] as const;
    return (
        <nav className="border-line grid grid-cols-3 border-t bg-white">
            {tabs.map(({ key, label, href, Icon }) => (
                <Link
                    key={key}
                    href={href}
                    className={'flex flex-col items-center gap-0.5 py-2 text-xs ' + (current === key ? 'text-brand font-bold' : 'text-ink-soft')}
                >
                    <Icon className="h-5 w-5" />
                    {label}
                </Link>
            ))}
        </nav>
    );
}
