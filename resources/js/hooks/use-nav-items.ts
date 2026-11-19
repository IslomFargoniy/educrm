import {
    BarChart3,
    BookOpen,
    Building2,
    CalendarDays,
    CalendarOff,
    ClipboardCheck,
    GraduationCap,
    Home,
    LayoutGrid,
    ShieldCheck,
    UserCog,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-react';
import { useSettingsTabs } from '@/components/settings-tabs';
import { useCan } from '@/hooks/use-can';
import { useT } from '@/lib/i18n';
import { dashboard, portal, reports, schedule } from '@/routes';
import admin from '@/routes/admin';
import attendance from '@/routes/attendance';
import cashboxes from '@/routes/cashboxes';
import groups from '@/routes/groups';
import leads from '@/routes/leads';
import students from '@/routes/students';
import teachers from '@/routes/teachers';
import type { NavItem } from '@/types';

/**
 * Navigation items the current user may see, shared by the sidebar and the mobile tab bar.
 */
export function useNavItems(): { main: NavItem[]; settings: NavItem[] } {
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
            shortTitle: t('Lessons'),
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

    const settingsTabs = useSettingsTabs();
    const settings: NavItem[] = [
        // Branches, courses, expense categories and Excel import share one page with tabs.
        settingsTabs.length > 0 && {
            title: t('Branches & rooms'),
            href: settingsTabs[0].href,
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
    ].filter(Boolean) as NavItem[];

    return { main, settings };
}
