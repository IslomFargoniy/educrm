import { router, usePage } from '@inertiajs/react';
import { Building2, Languages } from 'lucide-react';
import { NativeSelect } from '@/components/ui/native-select';
import { useT } from '@/lib/i18n';
import branch from '@/routes/branch';
import locale from '@/routes/locale';

export function BranchSwitcher() {
    const { branches, currentBranchId, auth } = usePage().props;
    const t = useT();
    const isSuperadmin = auth.roles.includes('superadmin');

    if (branches.length < 2 && !isSuperadmin) {
        return null;
    }

    return (
        <label className="flex items-center gap-1.5">
            <Building2 className="size-4 text-muted-foreground" />
            <NativeSelect
                aria-label={t('Branch')}
                className="h-8 w-auto max-w-44"
                value={currentBranchId ?? 'all'}
                onChange={(e) =>
                    router.post(
                        branch.switch().url,
                        { branch_id: e.target.value },
                        { preserveScroll: true },
                    )
                }
            >
                {isSuperadmin && (
                    <option value="all">{t('All branches')}</option>
                )}
                {branches.map((item) => (
                    <option key={item.id} value={item.id}>
                        {item.name}
                    </option>
                ))}
            </NativeSelect>
        </label>
    );
}

export function LocaleSwitcher({ className }: { className?: string }) {
    const { locale: current } = usePage().props;

    return (
        <label className={className ?? 'flex items-center gap-1.5'}>
            <Languages className="size-4 text-muted-foreground" />
            <NativeSelect
                aria-label="Language"
                className="h-8 w-auto"
                value={current}
                onChange={(e) =>
                    router.post(
                        locale.update().url,
                        { locale: e.target.value },
                        { preserveScroll: true },
                    )
                }
            >
                <option value="uz">O'zbekcha</option>
                <option value="ru">Русский</option>
                <option value="en">English</option>
            </NativeSelect>
        </label>
    );
}
