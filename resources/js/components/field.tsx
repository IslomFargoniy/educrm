import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export default function Field({
    label,
    error,
    hint,
    htmlFor,
    className,
    children,
}: {
    label?: ReactNode;
    error?: string;
    hint?: ReactNode;
    htmlFor?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid gap-1.5', className)}>
            {label && <Label htmlFor={htmlFor}>{label}</Label>}
            {children}
            {hint && !error && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}
