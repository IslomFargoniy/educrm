import { Link } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    Building2,
    CalendarDays,
    CalendarOff,
    ClipboardCheck,
    FileSpreadsheet,
    GraduationCap,
    Home,
    LayoutGrid,
    ShieldCheck,
    Tags,
    UserCog,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-react';
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
import { useT } from '@/lib/i18n';
import { dashboard, home, portal, reports, schedule } from '@/routes';
import admin from '@/routes/admin';
import attendance from '@/routes/attendance';
import cashboxes from '@/routes/cashboxes';
import groups from '@/routes/groups';
import leads from '@/routes/leads';
import students from '@/routes/students';
import teachers from '@/routes/teachers';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { can, hasRole } = useCan();
    const t = useT();
    const isPortalUser = hasRole('parent') || hasRole('student');
    const isTeacher = hasRole('teacher');

    const main: NavItem[] = [
        can('dashboard.view') && {
            title: t('Dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        (can('attendance.mark') || can('lessons.manage')) && {
            title: t('Lessons & attendance'),
            href: attendance.index(),
            icon: ClipboardCheck,
        },
        can('students.view') && {
            title: t('Students'),
            href: students.index(),
            icon: GraduationCap,
        },
        (can('groups.view') || isTeacher) && {
            title: t('Groups'),
            href: groups.index(),
            icon: BookOpen,
        },
        can('groups.view') && {
            title: t('Schedule'),
            href: schedule(),
            icon: CalendarDays,
        },
        can('leads.view') && {
            title: t('Leads'),
            href: leads.index(),
            icon: UserPlus,
        },
        can('teachers.view') && {
            title: t('Teachers'),
            href: teachers.index(),
            icon: Users,
        },
        can('cashboxes.view') && {
            title: t('Cashboxes'),
            href: cashboxes.index(),
            icon: Wallet,
        },
        can('reports.view') && {
            title: t('Reports'),
            href: reports(),
            icon: BarChart3,
        },
        isPortalUser && { title: t('My children'), href: portal(), icon: Home },
    ].filter(Boolean) as NavItem[];

    const settings: NavItem[] = [
        can('rooms.manage') && {
            title: t('Branches & rooms'),
            href: admin.branches.index(),
            icon: Building2,
        },
        can('users.manage') && {
            title: t('Users'),
            href: admin.users.index(),
            icon: UserCog,
        },
        can('roles.manage') && {
            title: t('Roles & permissions'),
            href: admin.roles.index(),
            icon: ShieldCheck,
        },
        can('holidays.manage') && {
            title: t('Holidays'),
            href: admin.holidays.index(),
            icon: CalendarOff,
        },
        can('settings.manage') && {
            title: t('Expense categories'),
            href: admin.expenseCategories.index(),
            icon: Tags,
        },
        can('import.run') && {
            title: t('Excel import'),
            href: admin.import.index(),
            icon: FileSpreadsheet,
        },
    ].filter(Boolean) as NavItem[];

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
