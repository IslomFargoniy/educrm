import type { ReactNode } from 'react';

export default function EmptyState({ children }: { children: ReactNode }) {
    return (
        <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
            {children}
        </div>
    );
}
