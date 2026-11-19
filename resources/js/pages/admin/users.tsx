import { Head, router } from '@inertiajs/react';
import { KeyRound, Pencil, Plus } from 'lucide-react';
import Field from '@/components/field';
import NameFields from '@/components/name-fields';
import FormDialog from '@/components/form-dialog';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import PhoneInput from '@/components/phone-input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatPhone } from '@/lib/format';
import { useT } from '@/lib/i18n';
import admin from '@/routes/admin';

type User = {
    id: number;
    name: string;
    first_name: string | null;
    last_name: string | null;
    phone: string;
    email: string | null;
    is_active: boolean;
    role: string;
    branch_ids: number[];
    branches: string[];
    personal_permissions: string[];
};

type Props = {
    users: User[];
    branches: { id: number; name: string }[];
    roles: string[];
    personalPermissions: string[];
};

export default function Users({
    users,
    branches,
    roles,
    personalPermissions,
}: Props) {
    const t = useT();

    return (
        <>
            <Head title={t('Users')} />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={t('Users')}
                    description={t(
                        'Staff and teachers. Parents get accounts from the student page.',
                    )}
                    actions={
                        <FormDialog
                            wide
                            title={t('New user')}
                            description={t(
                                'If the password is empty, it is generated and shown once.',
                            )}
                            form={admin.users.store.form()}
                            trigger={
                                <Button>
                                    <Plus />
                                    {t('New user')}
                                </Button>
                            }
                        >
                            {(errors) => (
                                <UserFields
                                    errors={errors}
                                    {...{
                                        branches,
                                        roles,
                                        personalPermissions,
                                    }}
                                />
                            )}
                        </FormDialog>
                    }
                />
                <div className="overflow-hidden rounded-xl border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-12 text-center">
                                    №
                                </TableHead>
                                <TableHead>{t('Name')}</TableHead>
                                <TableHead>{t('Role')}</TableHead>
                                <TableHead>{t('Branches')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.map((user, index) => (
                                <TableRow
                                    key={user.id}
                                    className={
                                        user.is_active ? '' : 'opacity-50'
                                    }
                                >
                                    <TableCell className="w-12 text-center text-muted-foreground tabular-nums">
                                        {index + 1}
                                    </TableCell>
                                    <TableCell>
                                        <div className="font-semibold">
                                            {user.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {formatPhone(user.phone)}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {t(`role.${user.role}`)}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {user.branches.join(', ') || '—'}
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap">
                                        <FormDialog
                                            wide
                                            title={t('Edit user')}
                                            form={admin.users.update.form(
                                                user.id,
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
                                                <UserFields
                                                    errors={errors}
                                                    user={user}
                                                    {...{
                                                        branches,
                                                        roles,
                                                        personalPermissions,
                                                    }}
                                                />
                                            )}
                                        </FormDialog>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            title={t('New password')}
                                            onClick={() =>
                                                confirm(
                                                    t(
                                                        'Generate a new password for :name?',
                                                        { name: user.name },
                                                    ),
                                                ) &&
                                                router.post(
                                                    admin.users.password(
                                                        user.id,
                                                    ).url,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <KeyRound />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

function UserFields({
    errors,
    user,
    branches,
    roles,
    personalPermissions,
}: { errors: Record<string, string>; user?: User } & Omit<Props, 'users'>) {
    const t = useT();

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <NameFields
                errors={errors}
                firstName={user?.first_name}
                lastName={user?.last_name}
            />
            <Field label={t('Phone')} error={errors.phone}>
                <PhoneInput name="phone" defaultValue={user?.phone} required />
            </Field>
            <Field label={t('Email (optional)')} error={errors.email}>
                <Input
                    name="email"
                    type="email"
                    defaultValue={user?.email ?? ''}
                />
            </Field>
            <Field
                label={
                    user
                        ? t('New password (optional)')
                        : t('Password (optional)')
                }
                error={errors.password}
            >
                <Input
                    name="password"
                    type="text"
                    autoComplete="new-password"
                />
            </Field>
            <Field label={t('Role')} error={errors.role}>
                <NativeSelect
                    name="role"
                    defaultValue={user?.role ?? 'teacher'}
                >
                    {roles.map((r) => (
                        <option key={r} value={r}>
                            {t(`role.${r}`)}
                        </option>
                    ))}
                </NativeSelect>
            </Field>
            {user && (
                <Field label={t('Status')}>
                    <NativeSelect
                        name="is_active"
                        defaultValue={user.is_active ? '1' : '0'}
                    >
                        <option value="1">{t('status.active')}</option>
                        <option value="0">{t('Blocked')}</option>
                    </NativeSelect>
                </Field>
            )}
            <Field
                label={t('Branches')}
                error={errors.branch_ids}
                className="sm:col-span-2"
            >
                <div className="flex flex-wrap gap-3">
                    {branches.map((b) => (
                        <Label
                            key={b.id}
                            className="flex items-center gap-1.5 font-normal"
                        >
                            <Checkbox
                                name="branch_ids[]"
                                value={String(b.id)}
                                defaultChecked={
                                    user
                                        ? user.branch_ids.includes(b.id)
                                        : branches.length === 1
                                }
                            />
                            {b.name}
                        </Label>
                    ))}
                </div>
            </Field>
            <Field
                label={t('Extra permissions for this user')}
                hint={t(
                    'For example, let a teacher see phone numbers even if the role does not allow it.',
                )}
                className="sm:col-span-2"
            >
                <div className="flex flex-wrap gap-3">
                    {personalPermissions.map((p) => (
                        <Label
                            key={p}
                            className="flex items-center gap-1.5 font-normal"
                        >
                            <Checkbox
                                name="personal_permissions[]"
                                value={p}
                                defaultChecked={user?.personal_permissions.includes(
                                    p,
                                )}
                            />
                            {t(`perm.${p}`)}
                        </Label>
                    ))}
                </div>
            </Field>
        </div>
    );
}

Users.layout = {
    breadcrumbs: [{ title: 'Users', href: admin.users.index() }],
};
