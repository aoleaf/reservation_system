// 画面名 → props を作る builder の表。
// すべての画面を BE のコントローラーに移したので、今は空（app/Http/Controllers）。本番では mocks/ ごと消す。
import type { MockData } from './seed';

export type ServerProps = Record<string, unknown>;
export type Builder = (state: MockData, serverProps: ServerProps) => object;

export const builders: Record<string, Builder> = {};
