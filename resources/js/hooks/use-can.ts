import { usePage } from '@inertiajs/react';

/**
 * Permission and role checks for showing menus and buttons.
 */
export function useCan(): {
    can: (permission: string) => boolean;
    hasRole: (role: string) => boolean;
} {
    const { auth } = usePage().props;

    return {
        can: (permission) => auth.permissions.includes(permission),
        hasRole: (role) => auth.roles.includes(role),
    };
}
