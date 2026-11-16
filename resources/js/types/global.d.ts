import type { Auth, Locale } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            locale: Locale;
            auth: Auth;
            branches: { id: number; name: string }[];
            currentBranchId: number | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
