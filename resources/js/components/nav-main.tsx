import { Link, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

const buttonClass =
    'h-10 gap-3 rounded-lg px-3 font-semibold text-sidebar-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground data-[active=true]:bg-sidebar-primary data-[active=true]:font-bold data-[active=true]:text-sidebar-primary-foreground data-[active=true]:hover:bg-sidebar-primary data-[active=true]:hover:text-sidebar-primary-foreground [&>svg]:size-[18px]';

/**
 * A sub-page is current when its path matches and its own query (e.g. ?category=new) is in the URL;
 * a plain path (the full list) is current only when the URL has none of its siblings' queries.
 */
function isChildActive(
    pageUrl: string,
    href: string,
    siblings: string[],
): boolean {
    const page = new URL(pageUrl, 'http://x');
    const target = new URL(href, 'http://x');

    if (page.pathname !== target.pathname) {
        return false;
    }

    const own = [...target.searchParams.entries()];

    if (own.length > 0) {
        return own.every(
            ([key, value]) => page.searchParams.get(key) === value,
        );
    }

    return !siblings.some((other) =>
        [...new URL(other, 'http://x').searchParams.entries()].some(
            ([key, value]) => page.searchParams.get(key) === value,
        ),
    );
}

function NavGroupItem({
    item,
}: {
    item: NavItem & { children: { title: string; href: string }[] };
}) {
    const { url } = usePage();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const hrefs = item.children.map((c) => c.href);
    const anyActive =
        item.children.some((c) => isChildActive(url, c.href, hrefs)) ||
        isCurrentOrParentUrl(item.href);
    const [open, setOpen] = useState(anyActive);

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                isActive={anyActive && !open}
                tooltip={{ children: item.title }}
                className={buttonClass}
                onClick={() => setOpen(!open)}
                aria-expanded={open}
            >
                {item.icon && <item.icon />}
                <span>{item.title}</span>
                <ChevronDown
                    className={cn(
                        'ml-auto size-4 transition-transform',
                        open && 'rotate-180',
                    )}
                />
            </SidebarMenuButton>
            {open && (
                <SidebarMenuSub>
                    {item.children.map((child) => (
                        <SidebarMenuSubItem key={child.href}>
                            <SidebarMenuSubButton
                                asChild
                                isActive={isChildActive(url, child.href, hrefs)}
                                className="text-sidebar-foreground data-[active=true]:bg-sidebar-primary data-[active=true]:font-bold data-[active=true]:text-sidebar-primary-foreground"
                            >
                                <Link href={child.href} prefetch>
                                    <span>{child.title}</span>
                                </Link>
                            </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                    ))}
                </SidebarMenuSub>
            )}
        </SidebarMenuItem>
    );
}

export function NavMain({ items, label }: { items: NavItem[]; label: string }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-3 py-1">
            <SidebarGroupLabel className="text-[11px] font-bold tracking-wider text-sidebar-muted uppercase">
                {label}
            </SidebarGroupLabel>
            <SidebarMenu className="gap-0.5">
                {items.map((item) =>
                    item.children && item.children.length > 0 ? (
                        <NavGroupItem
                            key={item.title}
                            item={{ ...item, children: item.children }}
                        />
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentOrParentUrl(item.href)}
                                tooltip={{ children: item.title }}
                                className={buttonClass}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
