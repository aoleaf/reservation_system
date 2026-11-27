// モック用の日付ヘルパー。すべて 'YYYY-MM-DD' / 'YYYY-MM' の文字列を受け取り、文字列を返す。
// new Date() は使わない（「今日」はストアの today）。
import { addDays as dfAddDays, addMonths, format, getDay, getDaysInMonth, parseISO } from 'date-fns';
import type { ISODate } from '@/types/models';

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

export const isISODate = (s: unknown): s is ISODate => typeof s === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(s) && !isNaN(parseISO(s).getTime());
export const isMonth = (s: unknown): s is string => typeof s === 'string' && /^\d{4}-(0[1-9]|1[0-2])$/.test(s);

export const weekdayJa = (d: ISODate) => WEEKDAYS[getDay(parseISO(d))];
export const isWeekendDate = (d: ISODate) => {
    const w = getDay(parseISO(d));
    return w === 0 || w === 6;
};
export const addDays = (d: ISODate, n: number): ISODate => format(dfAddDays(parseISO(d), n), 'yyyy-MM-dd');
export const monthOf = (d: ISODate) => d.slice(0, 7);
export const shiftMonth = (month: string, n: number) => format(addMonths(parseISO(`${month}-01`), n), 'yyyy-MM');
export const datesInMonth = (month: string): ISODate[] => {
    const n = getDaysInMonth(parseISO(`${month}-01`));
    return Array.from({ length: n }, (_, i) => `${month}-${String(i + 1).padStart(2, '0')}`);
};
/** 曜日の列（0=日）。カレンダーの空白マス用 */
export const weekdayIndex = (d: ISODate) => getDay(parseISO(d));

/** '11月21日（土）' */
export const dayLabel = (d: ISODate) => `${Number(d.slice(5, 7))}月${Number(d.slice(8, 10))}日（${weekdayJa(d)}）`;
/** '11/21（土）' */
export const shortDayLabel = (d: ISODate) => `${Number(d.slice(5, 7))}/${Number(d.slice(8, 10))}（${weekdayJa(d)}）`;
/** '2026年12月' */
export const monthLabel = (month: string) => `${month.slice(0, 4)}年${Number(month.slice(5, 7))}月`;
