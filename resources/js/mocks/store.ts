// モックのストア（zustand + persist / sessionStorage）。
// 変更用の関数を呼んでよいのは mocks/actions.ts と mocks/demo-bar.tsx だけ。
import type { ISODate, MemberMonth, Reservation, SlotId, SlotStatus } from '@/types/models';
import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';
import { createSeed, SEED_VERSION, type MockData, type Role } from './seed';

export type MockStore = MockData & {
    /** id・createdAt を付けて予約を追加し、付けた id を返す */
    addReservation: (r: Omit<Reservation, 'id' | 'createdAt'>) => Reservation;
    updateReservation: (id: string, patch: Partial<Reservation>) => void;
    setSlotStatus: (slotId: SlotId, status: SlotStatus) => void;
    upsertMemberMonth: (mm: MemberMonth) => void;
    setToday: (today: ISODate) => void;
    setRole: (role: Role) => void;
    /** DemoBar を開いているか（リセットしても変えない） */
    demoOpen: boolean;
    setDemoOpen: (open: boolean) => void;
    reset: () => void;
};

export const useMockStore = create<MockStore>()(
    persist(
        (set, get) => ({
            ...createSeed(),
            addReservation: (r) => {
                const seq = get().seq + 1;
                const res: Reservation = { ...r, id: `r${String(seq).padStart(4, '0')}`, createdAt: `${get().today}T12:00:00` };
                set((s) => ({ seq, reservations: [...s.reservations, res] }));
                return res;
            },
            updateReservation: (id, patch) => set((s) => ({ reservations: s.reservations.map((r) => (r.id === id ? { ...r, ...patch } : r)) })),
            setSlotStatus: (slotId, status) => set((s) => ({ slots: s.slots.map((x) => (x.id === slotId ? { ...x, status } : x)) })),
            upsertMemberMonth: (mm) =>
                set((s) => {
                    const others = s.memberMonths.filter((x) => !(x.memberId === mm.memberId && x.month === mm.month));
                    return { memberMonths: [...others, mm] };
                }),
            setToday: (today) => set({ today }),
            setRole: (role) => set({ role }),
            demoOpen: false,
            setDemoOpen: (demoOpen) => set({ demoOpen }),
            reset: () => set({ ...createSeed() }),
        }),
        {
            name: `tsuchinone-mock-v${SEED_VERSION}`,
            storage: createJSONStorage(() => sessionStorage),
        },
    ),
);
