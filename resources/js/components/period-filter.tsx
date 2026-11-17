import { router } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';

/**
 * From–to date filter that reloads the page with ?from=&to=.
 */
export default function PeriodFilter({
    from,
    to,
    only,
}: {
    from: string;
    to: string;
    only?: string[];
}) {
    const t = useT();
    const apply = (next: { from?: string; to?: string }) =>
        router.get(
            window.location.pathname,
            { from, to, ...next },
            { preserveScroll: true, preserveState: true, only },
        );

    return (
        <div className="flex flex-wrap items-center gap-2 text-sm">
            <span className="text-muted-foreground">{t('Period')}:</span>
            <Input
                type="date"
                value={from}
                onChange={(e) => apply({ from: e.target.value })}
                className="h-8 w-40"
            />
            <span>—</span>
            <Input
                type="date"
                value={to}
                onChange={(e) => apply({ to: e.target.value })}
                className="h-8 w-40"
            />
        </div>
    );
}
