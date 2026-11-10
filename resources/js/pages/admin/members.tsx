import AdminTabs from '@/components/admin-tabs';
import MobileShell from '@/components/mobile-shell';

export default function Members() {
    return (
        <MobileShell title="生徒一覧" variant="staff" footer={<AdminTabs current="members" />}>
            <p className="text-ink-soft">生徒一覧（準備中）</p>
        </MobileShell>
    );
}
