import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
    to: number | null;
};

export default function Pagination({ links }: { links: Paginated<unknown>['links'] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav className="flex flex-wrap gap-1">
            {links.map((link, index) => (
                <Link
                    key={index}
                    href={link.url ?? '#'}
                    preserveScroll
                    className={cn(
                        'rounded-md border px-3 py-1.5 text-sm',
                        link.active && 'border-primary bg-primary text-primary-foreground',
                        !link.url && 'pointer-events-none opacity-40',
                    )}
                    dangerouslySetInnerHTML={{ __html: link.label.replace('&laquo; Previous', '‹').replace('Next &raquo;', '›') }}
                />
            ))}
        </nav>
    );
}
