import type { CalendarDay } from '@/types/pages';
import type { ReactNode } from 'react';

type Props<T extends CalendarDay> = {
    /** 先頭の空白マスの数（1日の曜日） */
    leadingBlanks: number;
    days: T[];
    renderDay: (day: T) => ReactNode;
};

const HEAD = ['日', '月', '火', '水', '木', '金', '土'];

/** 月のカレンダー。日の中身は renderDay で差し替える（生徒の「来月を選ぶ」、スタッフの月カレンダー） */
export default function MonthCalendar<T extends CalendarDay>({ leadingBlanks, days, renderDay }: Props<T>) {
    return (
        <div className="grid grid-cols-7 gap-1">
            {HEAD.map((h, i) => (
                <div key={h} className={'pb-1 text-center text-xs font-bold ' + (i === 0 ? 'text-alert' : i === 6 ? 'text-brand' : 'text-ink-soft')}>
                    {h}
                </div>
            ))}
            {Array.from({ length: leadingBlanks }, (_, i) => (
                <div key={`b${i}`} />
            ))}
            {days.map((d) => (
                <div key={d.date} className="min-w-0">
                    {renderDay(d)}
                </div>
            ))}
        </div>
    );
}
