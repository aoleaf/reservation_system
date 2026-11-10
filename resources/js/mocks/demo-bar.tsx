/** デモ用の上の帯（見た目だけ。ストアへの接続は feat/mock-foundation で行う） */
export default function DemoBar() {
    return (
        <div className="bg-ink sticky top-0 z-50 flex flex-wrap items-center gap-2 px-3 py-1 text-[11px] text-white">
            <span className="font-bold">確認用の画面です</span>
        </div>
    );
}
