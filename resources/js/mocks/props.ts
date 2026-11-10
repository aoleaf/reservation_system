// 画面名 → props を作る builder の表。builder は純粋関数にする。
// 本番ではコントローラーがこの役目を引き継ぎ、types/pages.ts と同じ形の props を返す。
// selectors を呼んでよいのはここと mocks/actions.ts だけ。
import type { MockData } from './seed';

export type ServerProps = Record<string, unknown>;
export type Builder = (state: MockData, serverProps: ServerProps) => object;

export const builders: Record<string, Builder> = {};
