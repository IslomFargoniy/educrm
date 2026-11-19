import { Link } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import type { CSSProperties } from 'react';
import { useSidebar } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useNavItems } from '@/hooks/use-nav-items';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const MAX_TABS = 5;

const itemClass =
    'relative z-10 flex h-full min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-full text-[#1c1c1e] transition-colors duration-300 outline-none focus-visible:ring-2 focus-visible:ring-[#007AFF]/60 dark:text-white';
const activeClass = 'text-[#007AFF] dark:text-[#0A84FF]';
const labelClass =
    'max-w-full truncate px-1 text-[10px] leading-tight font-semibold';

/**
 * iOS-style floating "liquid glass" tab bar, shown on phones only.
 * Shows the first menu items; the last slot opens the full sidebar when there are more.
 */
export function MobileTabBar() {
    const t = useT();
    const { main, settings } = useNavItems();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { setOpenMobile } = useSidebar();

    const all = [...main, ...settings];
    const hasMore = all.length > MAX_TABS;
    const tabs = hasMore ? main.slice(0, MAX_TABS - 1) : all;
    const slots = tabs.length + (hasMore ? 1 : 0);
    const activeIndex = tabs.findIndex((item) =>
        isCurrentOrParentUrl(item.href),
    );

    if (slots === 0) {
        return null;
    }

    return (
        <nav
            aria-label={t('Menu')}
            className="fixed inset-x-4 bottom-[calc(env(safe-area-inset-bottom)+0.5rem)] z-40 md:hidden"
        >
            <div
                className={cn(
                    'relative flex h-[62px] items-stretch overflow-hidden rounded-full p-1',
                    'border border-white/80 bg-[#f9f9f9]/70 shadow-[0_10px_30px_-8px_rgba(0,0,0,0.25)] backdrop-blur-2xl backdrop-saturate-150',
                    'dark:border-white/15 dark:bg-[#2c2c2e]/70 dark:shadow-[0_10px_30px_-6px_rgba(0,0,0,0.6)]',
                    'before:pointer-events-none before:absolute before:inset-0 before:rounded-full before:bg-gradient-to-b before:from-white/40 before:to-transparent dark:before:from-white/[0.07]',
                )}
            >
                {activeIndex >= 0 && (
                    <span
                        aria-hidden
                        className="absolute inset-y-1 left-1 rounded-full border border-white/60 bg-black/[0.06] transition-transform duration-500 ease-[cubic-bezier(0.3,1.35,0.5,1)] dark:border-white/[0.08] dark:bg-black/30"
                        style={
                            {
                                width: `calc((100% - 0.5rem) / ${slots})`,
                                transform: `translateX(${activeIndex * 100}%)`,
                            } as CSSProperties
                        }
                    />
                )}

                {tabs.map((item, index) => {
                    const active = index === activeIndex;

                    return (
                        <Link
                            key={item.title}
                            href={item.href}
                            prefetch
                            aria-current={active ? 'page' : undefined}
                            className={cn(itemClass, active && activeClass)}
                        >
                            {item.icon && (
                                <item.icon
                                    className="size-6"
                                    strokeWidth={active ? 2.4 : 2}
                                />
                            )}
                            <span className={labelClass}>
                                {item.shortTitle ?? item.title}
                            </span>
                        </Link>
                    );
                })}

                {hasMore && (
                    <button
                        type="button"
                        onClick={() => setOpenMobile(true)}
                        className={itemClass}
                    >
                        <Menu className="size-6" />
                        <span className={labelClass}>{t('Menu')}</span>
                    </button>
                )}
            </div>
        </nav>
    );
}
