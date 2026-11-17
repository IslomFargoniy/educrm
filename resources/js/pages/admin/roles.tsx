import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';

type Role = { id: number; name: string; permissions: string[] };

export default function Roles({
    roles,
    groups,
}: {
    roles: Role[];
    groups: Record<string, string[]>;
}) {
    const t = useT();
    const [roleId, setRoleId] = useState(roles[0]?.id);
    const role = roles.find((r) => r.id === roleId) ?? roles[0];
    const [selected, setSelected] = useState<string[]>(role?.permissions ?? []);

    const choose = (next: Role) => {
        setRoleId(next.id);
        setSelected(next.permissions);
    };

    return (
        <>
            <Head title={t('Roles & permissions')} />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={t('Roles & permissions')}
                    description={t('Superadmin always has every permission.')}
                />
                <div className="flex flex-wrap gap-2">
                    {roles.map((r) => (
                        <Button
                            key={r.id}
                            variant={r.id === role?.id ? 'default' : 'outline'}
                            onClick={() => choose(r)}
                        >
                            {t(`role.${r.name}`)}
                        </Button>
                    ))}
                </div>
                {role && (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {Object.entries(groups).map(([group, permissions]) => (
                            <section
                                key={group}
                                className="rounded-xl border bg-card p-4"
                            >
                                <h2 className="mb-3 font-bold">
                                    {t(`permgroup.${group}`)}
                                </h2>
                                <div className="space-y-2">
                                    {permissions.map((p) => (
                                        <Label
                                            key={p}
                                            className={cn(
                                                'flex items-center gap-2 font-normal',
                                            )}
                                        >
                                            <Checkbox
                                                checked={selected.includes(p)}
                                                onCheckedChange={(v) =>
                                                    setSelected(
                                                        v === true
                                                            ? [...selected, p]
                                                            : selected.filter(
                                                                  (x) =>
                                                                      x !== p,
                                                              ),
                                                    )
                                                }
                                            />
                                            {t(`perm.${p}`)}
                                        </Label>
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                )}
                {role && (
                    <div>
                        <Button
                            onClick={() =>
                                router.put(
                                    admin.roles.update(role.id).url,
                                    { permissions: selected },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('Save')}
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

Roles.layout = {
    breadcrumbs: [{ title: 'Roles & permissions', href: admin.roles.index() }],
};
