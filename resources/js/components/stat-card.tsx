import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export default function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'default',
}: {
    label: ReactNode;
    value: ReactNode;
    hint?: ReactNode;
    icon?: LucideIcon;
    tone?: 'default' | 'primary' | 'highlight' | 'danger' | 'success';
}) {
    const isPrimary = tone === 'primary';

    return (
        <div
            className={cn(
                'flex flex-col gap-2 rounded-2xl border bg-card p-5',
                isPrimary && 'border-transparent bg-hero text-white',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <span
                    className={cn(
                        'text-sm font-bold',
                        isPrimary ? 'text-white/80' : 'text-muted-foreground',
                    )}
                >
                    {label}
                </span>
                {Icon && (
                    <span
                        className={cn(
                            'flex size-9 shrink-0 items-center justify-center rounded-xl',
                            isPrimary ? 'bg-white/10' : 'bg-secondary',
                            tone === 'primary' && 'text-highlight',
                            tone === 'highlight' && 'text-highlight',
                            tone === 'danger' && 'text-danger',
                            tone === 'success' && 'text-success',
                            tone === 'default' && 'text-secondary-foreground',
                        )}
                    >
                        <Icon className="size-[18px]" />
                    </span>
                )}
            </div>
            <div
                className={cn(
                    'text-[28px] leading-tight font-extrabold tracking-tight tabular-nums',
                    tone === 'danger' && 'text-danger',
                    tone === 'success' && 'text-success',
                )}
            >
                {value}
            </div>
            {hint && (
                <div
                    className={cn(
                        'text-xs',
                        isPrimary ? 'text-white/75' : 'text-muted-foreground',
                    )}
                >
                    {hint}
                </div>
            )}
        </div>
    );
}
