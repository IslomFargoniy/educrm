import AppLogoIcon from '@/components/app-logo-icon';
import { LocaleSwitcher } from '@/components/header-switchers';
import { useT } from '@/lib/i18n';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    const t = useT();

    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <div className="relative hidden flex-col justify-between overflow-hidden bg-primary p-10 text-white lg:flex dark:bg-sidebar">
                <div className="flex items-center gap-3">
                    <AppLogoIcon className="size-10 text-white" />
                    <div className="leading-tight">
                        <div className="text-2xl font-extrabold tracking-tight">Mensa</div>
                        <div className="text-sm text-white/80">Learning center</div>
                    </div>
                </div>
                <div className="relative z-10 max-w-md">
                    <div className="mb-4 size-4 rotate-45 bg-highlight" />
                    <p className="text-3xl leading-tight font-extrabold">
                        {t('Students, attendance and cash — in one system.')}
                    </p>
                </div>
                <AppLogoIcon className="absolute -right-24 -bottom-24 size-[28rem] text-white opacity-[0.06]" />
            </div>
            <div className="flex flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
                <div className="flex w-full max-w-sm justify-end">
                    <LocaleSwitcher />
                </div>
                <div className="w-full max-w-sm">
                    <div className="flex flex-col gap-8">
                        <div className="flex flex-col items-center gap-4 lg:items-start">
                            <div className="flex size-14 items-center justify-center rounded-2xl bg-primary lg:hidden">
                                <AppLogoIcon className="size-10 text-white" />
                            </div>
                            <div className="space-y-1 text-center lg:text-left">
                                <h1 className="text-2xl font-extrabold tracking-tight">{t(title ?? '')}</h1>
                                <p className="text-sm text-muted-foreground">{t(description ?? '')}</p>
                            </div>
                        </div>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
