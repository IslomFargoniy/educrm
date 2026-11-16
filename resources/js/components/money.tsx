import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Amount in so'm. "signed" colors debts red and prepaid money green.
 */
export default function Money({
    value,
    signed = false,
    className,
}: {
    value: number | null | undefined;
    signed?: boolean;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'tabular-nums whitespace-nowrap',
                signed && value !== null && value !== undefined && value < 0 && 'font-semibold text-danger',
                signed && value !== null && value !== undefined && value > 0 && 'font-semibold text-success',
                className,
            )}
        >
            {formatMoney(value)}
        </span>
    );
}
