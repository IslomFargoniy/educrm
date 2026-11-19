import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import RowNumber from '@/components/row-number';
import Field from '@/components/field';
import FormDialog from '@/components/form-dialog';
import Money from '@/components/money';
import SettingsTabs from '@/components/settings-tabs';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PhoneInput from '@/components/phone-input';
import { NativeSelect } from '@/components/ui/native-select';
import { useCan } from '@/hooks/use-can';
import { useT } from '@/lib/i18n';
import admin from '@/routes/admin';

type Branch = {
    id: number;
    name: string;
    address: string | null;
    phone: string | null;
    is_active: boolean;
    rooms: { id: number; name: string; capacity: number | null }[];
    cashboxes: {
        id: number;
        name: string;
        method: string;
        is_active: boolean;
        balance: number;
    }[];
};

export default function Branches({
    branches,
    methods,
    canManageBranches,
}: {
    branches: Branch[];
    methods: string[];
    canManageBranches: boolean;
}) {
    const t = useT();
    const { can } = useCan();

    return (
        <>
            <Head title={t('Branches & rooms')} />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <SettingsTabs />
                <PageHeader
                    title={t('Branches & rooms')}
                    actions={
                        canManageBranches && (
                            <FormDialog
                                title={t('New branch')}
                                description={t(
                                    'Cashboxes Cash, Card, Click and Payme are created automatically.',
                                )}
                                form={admin.branches.store.form()}
                                trigger={
                                    <Button>
                                        <Plus />
                                        {t('New branch')}
                                    </Button>
                                }
                            >
                                {(errors) => <BranchFields errors={errors} />}
                            </FormDialog>
                        )
                    }
                />
                {branches.map((branch) => (
                    <section
                        key={branch.id}
                        className="space-y-4 rounded-2xl border bg-card p-4"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h2 className="text-lg font-extrabold">
                                    {branch.name}{' '}
                                    {!branch.is_active && (
                                        <span className="text-sm text-muted-foreground">
                                            ({t('inactive')})
                                        </span>
                                    )}
                                </h2>
                                <div className="text-sm text-muted-foreground">
                                    {[branch.address, branch.phone]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </div>
                            </div>
                            {canManageBranches && (
                                <FormDialog
                                    title={t('Edit branch')}
                                    form={admin.branches.update.form(branch.id)}
                                    trigger={
                                        <Button size="sm" variant="outline">
                                            <Pencil />
                                            {t('Edit')}
                                        </Button>
                                    }
                                >
                                    {(errors) => (
                                        <BranchFields
                                            errors={errors}
                                            branch={branch}
                                        />
                                    )}
                                </FormDialog>
                            )}
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <h3 className="font-bold">{t('Rooms')}</h3>
                                    <FormDialog
                                        title={t('New room')}
                                        form={admin.rooms.store.form(branch.id)}
                                        trigger={
                                            <Button size="sm" variant="ghost">
                                                <Plus />
                                                {t('Room')}
                                            </Button>
                                        }
                                    >
                                        {(errors) => (
                                            <RoomFields errors={errors} />
                                        )}
                                    </FormDialog>
                                </div>
                                <ul className="divide-y rounded-xl border text-sm">
                                    {branch.rooms.length === 0 && (
                                        <li className="px-3 py-2 text-muted-foreground">
                                            —
                                        </li>
                                    )}
                                    {branch.rooms.map((room, index) => (
                                        <li
                                            key={room.id}
                                            className="flex items-center justify-between px-3 py-2"
                                        >
                                            <span>
                                                <RowNumber
                                                    value={index + 1}
                                                    className="mr-2"
                                                />
                                                {room.name}{' '}
                                                {room.capacity && (
                                                    <span className="text-muted-foreground">
                                                        ·{' '}
                                                        {t(':n seats', {
                                                            n: room.capacity,
                                                        })}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="flex gap-1">
                                                <FormDialog
                                                    title={t('Edit room')}
                                                    form={admin.rooms.update.form(
                                                        room.id,
                                                    )}
                                                    trigger={
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                        >
                                                            <Pencil />
                                                        </Button>
                                                    }
                                                >
                                                    {(errors) => (
                                                        <RoomFields
                                                            errors={errors}
                                                            room={room}
                                                        />
                                                    )}
                                                </FormDialog>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        confirm(
                                                            t(
                                                                'Delete this room?',
                                                            ),
                                                        ) &&
                                                        router.delete(
                                                            admin.rooms.destroy(
                                                                room.id,
                                                            ).url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <h3 className="font-bold">
                                        {t('Cashboxes')}
                                    </h3>
                                    {can('settings.manage') && (
                                        <FormDialog
                                            title={t('New cashbox')}
                                            form={admin.cashboxes.store.form(
                                                branch.id,
                                            )}
                                            trigger={
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    <Plus />
                                                    {t('Cashbox')}
                                                </Button>
                                            }
                                        >
                                            {(errors) => (
                                                <>
                                                    <Field
                                                        label={t('Name')}
                                                        error={errors.name}
                                                    >
                                                        <Input
                                                            name="name"
                                                            required
                                                        />
                                                    </Field>
                                                    <Field
                                                        label={t(
                                                            'Payment method',
                                                        )}
                                                        error={errors.method}
                                                    >
                                                        <NativeSelect name="method">
                                                            {methods.map(
                                                                (m) => (
                                                                    <option
                                                                        key={m}
                                                                        value={
                                                                            m
                                                                        }
                                                                    >
                                                                        {t(
                                                                            `method.${m}`,
                                                                        )}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </NativeSelect>
                                                    </Field>
                                                </>
                                            )}
                                        </FormDialog>
                                    )}
                                </div>
                                <ul className="divide-y rounded-xl border text-sm">
                                    {branch.cashboxes.map((c, index) => (
                                        <li
                                            key={c.id}
                                            className={`flex items-center justify-between px-3 py-2 ${c.is_active ? '' : 'opacity-50'}`}
                                        >
                                            <span>
                                                <RowNumber
                                                    value={index + 1}
                                                    className="mr-2"
                                                />
                                                {c.name}{' '}
                                                <span className="text-muted-foreground">
                                                    · {t(`method.${c.method}`)}
                                                </span>
                                            </span>
                                            <span className="flex items-center gap-2">
                                                <Money value={c.balance} />
                                                {can('settings.manage') && (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            router.put(
                                                                admin.cashboxes.update(
                                                                    c.id,
                                                                ).url,
                                                                {
                                                                    name: c.name,
                                                                    is_active:
                                                                        !c.is_active,
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {c.is_active
                                                            ? t('Disable')
                                                            : t('Enable')}
                                                    </Button>
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </section>
                ))}
            </div>
        </>
    );
}

function BranchFields({
    errors,
    branch,
}: {
    errors: Record<string, string>;
    branch?: Branch;
}) {
    const t = useT();

    return (
        <>
            <Field label={t('Name')} error={errors.name}>
                <Input name="name" defaultValue={branch?.name} required />
            </Field>
            <Field label={t('Address')} error={errors.address}>
                <Input name="address" defaultValue={branch?.address ?? ''} />
            </Field>
            <Field label={t('Phone')} error={errors.phone}>
                <PhoneInput name="phone" defaultValue={branch?.phone ?? ''} />
            </Field>
            {branch && (
                <Field label={t('Status')}>
                    <NativeSelect
                        name="is_active"
                        defaultValue={branch.is_active ? '1' : '0'}
                    >
                        <option value="1">{t('status.active')}</option>
                        <option value="0">{t('inactive')}</option>
                    </NativeSelect>
                </Field>
            )}
        </>
    );
}

function RoomFields({
    errors,
    room,
}: {
    errors: Record<string, string>;
    room?: { name: string; capacity: number | null };
}) {
    const t = useT();

    return (
        <>
            <Field label={t('Name')} error={errors.name}>
                <Input name="name" defaultValue={room?.name} required />
            </Field>
            <Field label={t('Seats')} error={errors.capacity}>
                <Input
                    name="capacity"
                    type="number"
                    min={1}
                    defaultValue={room?.capacity ?? ''}
                />
            </Field>
        </>
    );
}

Branches.layout = {
    breadcrumbs: [{ title: 'Branches & rooms', href: admin.branches.index() }],
};
