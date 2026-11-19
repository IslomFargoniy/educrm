import { router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime, formatPhone } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import students from '@/routes/students';

/** Colour of each call result; the label always goes with it (never colour alone). */
export const CALL_STATUS_COLORS: Record<string, string> = {
    cancelled: 'bg-red-600',
    paid: 'bg-green-600',
    not_processed: 'bg-white border border-muted-foreground/40',
    initial: 'bg-white border border-muted-foreground/40',
    call_back: 'bg-red-600',
    no_answer: 'bg-blue-700',
};

/**
 * The phone number as a tag coloured by the last call result; a click picks a new result.
 */
export default function CallStatusTag({
    studentId,
    phone,
    status,
    at,
    statuses,
}: {
    studentId: number;
    phone: string | null;
    status: string | null;
    at: string | null;
    statuses: string[];
}) {
    const t = useT();
    const save = (next: string | null) =>
        router.patch(
            students.callStatus(studentId).url,
            { call_status: next },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    onClick={(e) => e.stopPropagation()}
                    className={cn(
                        'inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-semibold whitespace-nowrap tabular-nums hover:border-primary',
                    )}
                    title={
                        status
                            ? `${t(`call.${status}`)}${at ? ` · ${formatDateTime(at)}` : ''}`
                            : t('Mark the call result')
                    }
                >
                    <span
                        className={cn(
                            'size-2.5 shrink-0 rounded-full',
                            status
                                ? CALL_STATUS_COLORS[status]
                                : 'border border-muted-foreground/50',
                        )}
                    />
                    {phone ? formatPhone(phone) : '—'}
                    {status && (
                        <span className="font-normal text-muted-foreground">
                            · {t(`call.${status}`)}
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                onClick={(e) => e.stopPropagation()}
            >
                {phone && (
                    <DropdownMenuItem asChild>
                        <a href={`tel:${phone}`}>📞 {t('Call')}</a>
                    </DropdownMenuItem>
                )}
                {statuses.map((value) => (
                    <DropdownMenuItem
                        key={value}
                        onSelect={() => save(value)}
                        className="gap-3 py-2"
                    >
                        <span className="flex size-8 items-center justify-center rounded-lg bg-muted">
                            <span
                                className={cn(
                                    'size-4 rounded-full',
                                    CALL_STATUS_COLORS[value],
                                )}
                            />
                        </span>
                        {t(`call.${value}`)}
                        {status === value && (
                            <Check className="ml-auto size-4" />
                        )}
                    </DropdownMenuItem>
                ))}
                <DropdownMenuItem
                    onSelect={() => save(null)}
                    disabled={!status}
                    className="text-muted-foreground"
                >
                    {t('Back to the initial state')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
