import { Link } from '@inertiajs/react';
import { Smartphone } from 'lucide-react';
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
import { useCan } from '@/hooks/use-can';
import { useNavItems } from '@/hooks/use-nav-items';
import { useT } from '@/lib/i18n';
import { home } from '@/routes';

export function AppSidebar() {
    const t = useT();
    const { main, settings } = useNavItems();
    const { hasRole } = useCan();
    // The Android app is for staff; parents and students use the web portal.
    const isPortalUser = hasRole('parent') || hasRole('student');

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
                {!isPortalUser && (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                tooltip={{ children: t('Android app') }}
                                className="h-10 gap-3 rounded-lg px-3 font-semibold text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground [&>svg]:size-[18px]"
                            >
                                <a href="/mensa.apk" download="mensa.apk">
                                    <Smartphone />
                                    <span>{t('Android app')}</span>
                                </a>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
