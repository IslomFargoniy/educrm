import { Badge } from '@/components/ui/badge';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const tones: Record<string, string> = {
    active: 'bg-success/15 text-success',
    trial: 'bg-highlight/20 text-highlight-foreground dark:text-highlight',
    transferred: 'bg-secondary text-secondary-foreground',
    left: 'bg-muted text-muted-foreground',
    graduated: 'bg-primary/15 text-primary',
    frozen: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    planned: 'bg-secondary text-secondary-foreground',
    held: 'bg-success/15 text-success',
    cancelled: 'bg-muted text-muted-foreground line-through',
    present: 'bg-success/15 text-success',
    absent: 'bg-danger/15 text-danger',
    excused: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    new: 'bg-highlight/20 text-highlight-foreground dark:text-highlight',
    contacted: 'bg-secondary text-secondary-foreground',
    converted: 'bg-success/15 text-success',
    rejected: 'bg-muted text-muted-foreground',
    archived: 'bg-muted text-muted-foreground',
};

export default function StatusBadge({
    status,
    className,
}: {
    status: string;
    className?: string;
}) {
    const t = useT();

    return (
        <Badge
            variant="secondary"
            className={cn('border-0 font-semibold', tones[status], className)}
        >
            {t(`status.${status}`)}
        </Badge>
    );
}
