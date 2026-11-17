import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import AdminTabs, { type AdminTab } from './admin-tabs';

type Props = {
    title: string;
    current: AdminTab;
    /** 戻る先の URL。無ければ戻るボタンを出さない */
    back?: string;
    /** タイトルの右に置くもの（ボタンなど） */
    actions?: ReactNode;
    children: ReactNode;
};

/** スタッフ画面の外枠。PC を基本にし、スマホ（md 未満）では下のタブに切り替える */
export default function AdminShell({ title, current, back, actions, children }: Props) {
    return (
        <div className="bg-paper text-ink flex min-h-screen flex-col">
            <Head title={title} />
            <header className="bg-staff sticky top-0 z-20 text-white">
                <div className="mx-auto flex h-14 max-w-6xl items-center gap-4 px-4 md:px-8">
                    <span className="font-mincho shrink-0 font-bold">つちのね 管理</span>
                    <div className="hidden md:block">
                        <AdminTabs current={current} variant="top" />
                    </div>
                </div>
            </header>
            <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-5 pb-24 md:px-8 md:py-8">
                <div className="mb-4 flex items-center gap-2 md:mb-6">
                    {back && (
                        <Link href={back} className="-ml-2 flex h-9 w-9 items-center justify-center rounded-full" aria-label="戻る">
                            <ChevronLeft className="h-5 w-5" />
                        </Link>
                    )}
                    <h1 className="text-lg font-bold md:text-xl">{title}</h1>
                    {actions && <div className="ml-auto">{actions}</div>}
                </div>
                {children}
            </main>
            <div className="sticky bottom-0 z-20 md:hidden">
                <AdminTabs current={current} />
            </div>
        </div>
    );
}
