import { CalendarCheck, Users, Wallet } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import AppLogoIcon from '@/components/app-logo-icon';
import { LocaleSwitcher } from '@/components/header-switchers';
import { ThemeSwitcher } from '@/components/theme-switcher';
import { useT } from '@/lib/i18n';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const t = useT();

    const features = [
        { icon: Users, label: t('Students and groups') },
        { icon: CalendarCheck, label: t('Attendance and lessons') },
        { icon: Wallet, label: t('Payments and cashboxes') },
    ];

    return (
        <div className="grid min-h-svh bg-background lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            <aside className="relative hidden flex-col justify-between overflow-hidden bg-sidebar p-10 text-sidebar-foreground lg:flex lg:border-r lg:border-sidebar-border">
                <div className="flex items-center">
                    <AppLogo />
                </div>

                <div className="relative z-10 max-w-md space-y-8">
                    <div className="space-y-4">
                        <div className="size-4 rotate-45 bg-highlight" />
                        <p className="text-3xl leading-tight font-extrabold text-white">
                            {t(
                                'Students, attendance and cash — in one system.',
                            )}
                        </p>
                    </div>
                    <ul className="space-y-3">
                        {features.map(({ icon: Icon, label }) => (
                            <li
                                key={label}
                                className="flex items-center gap-3 text-sm font-semibold"
                            >
                                <span className="flex size-9 items-center justify-center rounded-lg bg-sidebar-accent text-highlight">
                                    <Icon className="size-4" />
                                </span>
                                {label}
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative z-10 text-xs text-sidebar-muted">
                    © {new Date().getFullYear()} EduCrm
                </p>

                <AppLogoIcon className="absolute -right-24 -bottom-24 size-[28rem] text-white opacity-[0.05]" />
            </aside>

            <main className="flex flex-col p-4 sm:p-6 md:p-10">
                <div className="flex items-center justify-end gap-2">
                    <LocaleSwitcher />
                    <ThemeSwitcher />
                </div>

                <div className="flex flex-1 items-center justify-center py-8">
                    <div className="w-full max-w-md rounded-2xl border bg-card p-6 shadow-sm sm:p-8">
                        <div className="flex flex-col gap-8">
                            <div className="flex flex-col items-center gap-4 sm:items-start">
                                <div className="flex size-14 items-center justify-center rounded-2xl bg-hero lg:hidden">
                                    <AppLogoIcon className="size-9 text-white" />
                                </div>
                                <div className="space-y-1 text-center sm:text-left">
                                    <h1 className="text-2xl font-extrabold tracking-tight">
                                        {t(title ?? '')}
                                    </h1>
                                    <p className="text-sm text-muted-foreground">
                                        {t(description ?? '')}
                                    </p>
                                </div>
                            </div>
                            {children}
                        </div>
                    </div>
                </div>
            </main>
        </div>
    );
}
