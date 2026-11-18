import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Light / dark / system switch shown in the app header.
 */
export function ThemeSwitcher({ className }: { className?: string }) {
    const { appearance, updateAppearance } = useAppearance();
    const t = useT();

    const options: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: t('Light') },
        { value: 'dark', icon: Moon, label: t('Dark') },
        { value: 'system', icon: Monitor, label: t('System') },
    ];

    return (
        <div
            role="group"
            aria-label={t('Theme')}
            className={cn(
                'inline-flex gap-0.5 rounded-xl border bg-background p-0.5',
                className,
            )}
        >
            {options.map(({ value, icon: Icon, label }) => {
                const isActive = appearance === value;

                return (
                    <button
                        key={value}
                        type="button"
                        title={label}
                        aria-label={label}
                        aria-pressed={isActive}
                        onClick={() => updateAppearance(value)}
                        className={cn(
                            'flex h-8 items-center gap-1.5 rounded-[10px] px-2.5 text-xs font-bold transition-colors',
                            isActive
                                ? 'bg-card text-primary shadow-xs dark:bg-hero dark:text-white'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <Icon className="size-4" />
                        <span className="hidden xl:inline">{label}</span>
                    </button>
                );
            })}
        </div>
    );
}
