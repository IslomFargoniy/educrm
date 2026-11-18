import { Breadcrumbs } from '@/components/breadcrumbs';
import { BranchSwitcher, LocaleSwitcher } from '@/components/header-switchers';
import { ThemeSwitcher } from '@/components/theme-switcher';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="no-print sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b bg-card/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-card/80 md:px-6">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1 size-9 rounded-lg border" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex items-center gap-2 sm:gap-3">
                <BranchSwitcher />
                <LocaleSwitcher className="hidden items-center gap-1.5 sm:flex" />
                <ThemeSwitcher />
            </div>
        </header>
    );
}
