import AdminTabs from '@/components/admin-tabs';
import MobileShell from '@/components/mobile-shell';

export default function MemberShow() {
    return (
        <MobileShell title="生徒の詳細" variant="staff" footer={<AdminTabs current="members" />}>
            <p className="text-ink-soft">生徒の詳細（準備中）</p>
        </MobileShell>
    );
}
