import { createInertiaApp, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { translate } from '@/lib/i18n';
import type { Locale } from '@/types';

const appName = import.meta.env.VITE_APP_NAME || 'EduCrm';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster richColors position="top-right" />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#F7941D',
    },
});

// Server errors as a short message instead of the raw error page in a modal.
// 503 happens for a few seconds while a new version is installed: nothing was saved, the action can be repeated.
router.on('httpException', (event) => {
    const status = event.detail.response.status;
    const lang = document.documentElement.lang.slice(0, 2);
    const locale = (['uz', 'ru', 'en'].includes(lang) ? lang : 'uz') as Locale;
    const message =
        status === 503
            ? 'The system is being updated. Nothing was saved — try again in half a minute.'
            : status === 419
              ? 'The page has expired. Refresh the page and try again.'
              : status >= 500
                ? 'Something went wrong on the server. Nothing was saved — try again.'
                : null;

    if (message === null) {
        return;
    }

    event.preventDefault();
    toast.error(translate(locale, message), { duration: 8000 });
});

// This will set light / dark mode on load...
initializeTheme();
