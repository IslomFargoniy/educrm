import { cn } from '@/lib/utils';

/**
 * Row number (№) in front of a list item, matching the numbered tables.
 */
export default function RowNumber({
    value,
    className,
}: {
    value: number;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-block w-6 shrink-0 text-right text-xs text-muted-foreground tabular-nums',
                className,
            )}
        >
            {value}
        </span>
    );
}
