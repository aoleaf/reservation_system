import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    title: string;
    variant?: 'customer' | 'staff';
    /** 戻る先の URL。無ければ戻るボタンを出さない */
    back?: string;
    /** 画面の下に固定して出すもの（スタッフのタブなど） */
    footer?: ReactNode;
    children: ReactNode;
};

/** 全画面の外枠。PC でも中央に最大 420px で表示する */
export default function MobileShell({ title, variant = 'customer', back, footer, children }: Props) {
    const staff = variant === 'staff';
    return (
        <div className="bg-line/60 min-h-screen">
            <Head title={title} />
            <div className="bg-paper text-ink relative mx-auto flex min-h-screen w-full max-w-[420px] flex-col shadow-sm">
                <header
                    className={
                        'sticky top-0 z-20 flex h-12 items-center gap-1 px-2 ' +
                        (staff ? 'bg-staff text-white' : 'border-line bg-paper text-ink border-b')
                    }
                >
                    {back ? (
                        <Link href={back} className="flex h-10 w-10 items-center justify-center rounded-full" aria-label="戻る">
                            <ChevronLeft className="h-5 w-5" />
                        </Link>
                    ) : (
                        <span className="w-2" />
                    )}
                    <h1 className={'truncate text-base font-bold ' + (staff ? '' : 'font-mincho')}>{title}</h1>
                    {staff && <span className="ml-auto pr-2 text-xs opacity-70">スタッフ</span>}
                </header>
                <main className={'flex-1 px-4 py-4 ' + (footer ? 'pb-20' : '')}>{children}</main>
                {footer && <div className="sticky bottom-0 z-20">{footer}</div>}
            </div>
        </div>
    );
}
