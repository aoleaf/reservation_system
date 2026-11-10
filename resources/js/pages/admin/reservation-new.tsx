import AdminTabs from '@/components/admin-tabs';
import MobileShell from '@/components/mobile-shell';

export default function ReservationNew() {
    return (
        <MobileShell title="予約の入力" variant="staff" footer={<AdminTabs current="today" />}>
            <p className="text-ink-soft">予約の入力（準備中）</p>
        </MobileShell>
    );
}
