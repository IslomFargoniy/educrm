import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center rounded-xl bg-highlight">
                <AppLogoIcon className="size-6 text-primary dark:text-[#0b0f7e]" />
            </div>
            <div className="ml-1.5 grid flex-1 text-left leading-tight">
                <span className="truncate text-lg font-extrabold tracking-tight text-white">
                    Mensa
                </span>
                <span className="truncate text-xs text-sidebar-muted">
                    Learning center
                </span>
            </div>
        </>
    );
}
