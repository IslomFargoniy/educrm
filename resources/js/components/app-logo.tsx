import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-white/10">
                <AppLogoIcon className="size-6 text-white" />
            </div>
            <div className="ml-1 grid flex-1 text-left leading-tight">
                <span className="truncate text-base font-extrabold tracking-tight text-white">
                    Mensa
                </span>
                <span className="truncate text-[10px] text-sidebar-foreground/80">
                    Learning center
                </span>
            </div>
        </>
    );
}
