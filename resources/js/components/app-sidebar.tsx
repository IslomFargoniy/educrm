import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useNavItems } from '@/hooks/use-nav-items';
import { useT } from '@/lib/i18n';
import { home } from '@/routes';

export function AppSidebar() {
    const t = useT();
    const { main, settings } = useNavItems();

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="px-3 pt-4 pb-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="hover:bg-transparent"
                        >
                            <Link href={home()}>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={main} label={t('Menu')} />
                {settings.length > 0 && (
                    <NavMain items={settings} label={t('Settings')} />
                )}
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-accent/60 p-3">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
