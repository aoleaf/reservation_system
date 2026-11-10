type Props = { monthly: number; trial: number; capacity: number; compact?: boolean };

/** 6マスを左から「月謝→体験→空き」の順に塗る。色だけでなく明度でも区別し、文字でも書く */
export default function SeatMeter({ monthly, trial, capacity, compact = false }: Props) {
    const cells = Array.from({ length: capacity }, (_, i) => (i < monthly ? 'monthly' : i < monthly + trial ? 'trial' : 'free'));
    return (
        <div className="flex items-center gap-2" aria-label={`月謝${monthly}・体験${trial}／${capacity}`}>
            <div className={'flex ' + (compact ? 'gap-px' : 'gap-0.5')}>
                {cells.map((c, i) => (
                    <span
                        key={i}
                        className={
                            (compact ? 'h-2 w-1.5 ' : 'h-3.5 w-3 ') +
                            (compact ? 'rounded-[1px] border ' : 'rounded-[2px] border ') +
                            (c === 'monthly' ? 'border-brand bg-brand' : c === 'trial' ? 'border-brand/60 bg-brand-soft' : 'border-line bg-white')
                        }
                    />
                ))}
            </div>
            {!compact && (
                <span className="text-ink-soft text-xs whitespace-nowrap">
                    月謝{monthly}・体験{trial}／{capacity}
                </span>
            )}
        </div>
    );
}
