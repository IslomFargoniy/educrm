import { Link } from '@inertiajs/react';
import { useCan } from '@/hooks/use-can';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';

/**
 * Tabs shared by the branch settings pages: branches & rooms, courses, expense categories, Excel import.
 */
export function useSettingsTabs() {
    const t = useT();
    const { can } = useCan();

    return [
        can('rooms.manage') && {
            title: t('Branches & rooms'),
            href: admin.branches.index(),
        },
        can('settings.manage') && {
            title: t('Courses'),
            href: admin.courses.index(),
        },
        can('settings.manage') && {
            title: t('Expense categories'),
            href: admin.expenseCategories.index(),
        },
        can('import.run') && {
            title: t('Excel import'),
            href: admin.import.index(),
        },
    ].filter(Boolean) as {
        title: string;
        href: ReturnType<typeof admin.branches.index>;
    }[];
}

export default function SettingsTabs() {
    const tabs = useSettingsTabs();
    const { isCurrentUrl } = useCurrentUrl();

    if (tabs.length < 2) {
        return null;
    }

    return (
        <nav className="-mt-1 flex gap-1 overflow-x-auto border-b">
            {tabs.map((tab) => (
                <Link
                    key={tab.title}
                    href={tab.href}
                    prefetch
                    className={cn(
                        '-mb-px shrink-0 border-b-2 px-3 py-2 text-sm font-semibold whitespace-nowrap',
                        isCurrentUrl(tab.href)
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {tab.title}
                </Link>
            ))}
        </nav>
    );
}
