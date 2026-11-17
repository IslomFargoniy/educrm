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
    return (
        <div
            className={cn(
                'rounded-xl border bg-card p-4',
                tone === 'primary' &&
                    'border-primary bg-primary text-primary-foreground',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <span
                    className={cn(
                        'text-xs font-semibold uppercase',
                        tone === 'primary'
                            ? 'text-primary-foreground/80'
                            : 'text-muted-foreground',
                    )}
                >
                    {label}
                </span>
                {Icon && (
                    <Icon
                        className={cn(
                            'size-4',
                            tone === 'highlight' && 'text-highlight',
                            tone === 'danger' && 'text-danger',
                            tone === 'success' && 'text-success',
                            tone === 'default' && 'text-muted-foreground',
                        )}
                    />
                )}
            </div>
            <div
                className={cn(
                    'mt-2 text-2xl font-extrabold tracking-tight',
                    tone === 'danger' && 'text-danger',
                    tone === 'success' && 'text-success',
                )}
            >
                {value}
            </div>
            {hint && (
                <div
                    className={cn(
                        'mt-1 text-xs',
                        tone === 'primary'
                            ? 'text-primary-foreground/80'
                            : 'text-muted-foreground',
                    )}
                >
                    {hint}
                </div>
            )}
        </div>
    );
}
