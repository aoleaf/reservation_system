import AdminTabs from '@/components/admin-tabs';
import MobileShell from '@/components/mobile-shell';

export default function Slots() {
    return (
        <MobileShell title="カレンダー" variant="staff" footer={<AdminTabs current="slots" />}>
            <p className="text-ink-soft">カレンダー（準備中）</p>
        </MobileShell>
    );
}
