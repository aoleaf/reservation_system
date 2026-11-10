import AdminTabs from '@/components/admin-tabs';
import MobileShell from '@/components/mobile-shell';

export default function Today() {
    return (
        <MobileShell title="今日の予約" variant="staff" footer={<AdminTabs current="today" />}>
            <p className="text-ink-soft">今日の予約（準備中）</p>
        </MobileShell>
    );
}
