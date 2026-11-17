import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/empty-state';
import Field from '@/components/field';
import FormDialog from '@/components/form-dialog';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCan } from '@/hooks/use-can';
import { today } from '@/lib/format';
import { useT } from '@/lib/i18n';
import students from '@/routes/students';

type Student = {
    id: number;
    full_name: string;
    phone: string | null;
    groups: { name: string; status: string }[];
    balance: number;
};

type Props = {
    students: Paginated<Student>;
    filters: { q?: string; group_id?: string; status?: string };
    groups: { id: number; name: string }[];
};

export default function StudentsIndex({
    students: list,
    filters,
    groups,
}: Props) {
    const t = useT();
    const { can } = useCan();
    const [q, setQ] = useState(filters.q ?? '');
    const apply = (next: Partial<Props['filters']>) =>
        router.get(
            students.index().url,
            { ...filters, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title={t('Students')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('Students')}
                    description={t(':count students', { count: list.total })}
                    actions={
                        can('students.create') && (
                            <NewStudentDialog groups={groups} />
                        )
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ q });
                        }}
                        className="relative"
                    >
                        <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder={t('Name or phone')}
                            className="w-64 pl-8"
                        />
                    </form>
                    <NativeSelect
                        className="w-48"
                        value={filters.group_id ?? ''}
                        onChange={(e) =>
                            apply({ group_id: e.target.value || undefined })
                        }
                    >
                        <option value="">{t('All groups')}</option>
                        {groups.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        className="w-44"
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            apply({ status: e.target.value || undefined })
                        }
                    >
                        <option value="">{t('All')}</option>
                        <option value="studying">{t('Studying')}</option>
                        <option value="debtor">{t('Debtors')}</option>
                        <option value="left">{t('Left')}</option>
                    </NativeSelect>
                </div>

                {list.data.length === 0 ? (
                    <EmptyState>{t('No students found.')}</EmptyState>
                ) : (
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Student')}</TableHead>
                                    <TableHead>{t('Phone')}</TableHead>
                                    <TableHead>{t('Groups')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Balance')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {list.data.map((s) => (
                                    <TableRow
                                        key={s.id}
                                        className="cursor-pointer"
                                        onClick={() =>
                                            router.visit(
                                                students.show(s.id).url,
                                            )
                                        }
                                    >
                                        <TableCell className="font-semibold">
                                            <Link
                                                href={students.show(s.id)}
                                                className="hover:text-primary"
                                            >
                                                {s.full_name}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {s.phone ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {s.groups
                                                .map((g) => g.name)
                                                .join(', ') || '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Money value={s.balance} signed />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination links={list.links} />
            </div>
        </>
    );
}

function NewStudentDialog({ groups }: { groups: Props['groups'] }) {
    const t = useT();

    return (
        <FormDialog
            wide
            title={t('New student')}
            form={students.store.form()}
            trigger={
                <Button>
                    <Plus />
                    {t('Add student')}
                </Button>
            }
        >
            {(errors) => (
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label={t('Full name')}
                        error={errors.full_name}
                        className="sm:col-span-2"
                    >
                        <Input name="full_name" required autoFocus />
                    </Field>
                    <Field label={t('Phone')} error={errors.phone}>
                        <Input
                            name="phone"
                            type="tel"
                            placeholder="+998 90 123 45 67"
                        />
                    </Field>
                    <Field label={t('Birth date')} error={errors.birth_date}>
                        <Input name="birth_date" type="date" />
                    </Field>
                    <Field
                        label={t('Parent name')}
                        error={errors.guardian_name}
                    >
                        <Input name="guardian_name" />
                    </Field>
                    <Field
                        label={t('Parent phone')}
                        error={errors.guardian_phone}
                        hint={t('A parent account is created with this phone.')}
                    >
                        <Input
                            name="guardian_phone"
                            type="tel"
                            placeholder="+998 90 123 45 67"
                        />
                    </Field>
                    <Field label={t('Group')} error={errors.group_id}>
                        <NativeSelect name="group_id" defaultValue="">
                            <option value="">{t('Later')}</option>
                            {groups.map((g) => (
                                <option key={g.id} value={g.id}>
                                    {g.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <Field label={t('Joined on')} error={errors.joined_at}>
                        <Input
                            name="joined_at"
                            type="date"
                            defaultValue={today()}
                        />
                    </Field>
                    <Field
                        label={t('Trial lessons')}
                        error={errors.trial_lessons}
                        hint={t('0 — no trial')}
                    >
                        <Input
                            name="trial_lessons"
                            type="number"
                            min={0}
                            max={30}
                            defaultValue={1}
                        />
                    </Field>
                    <Field
                        label={t('Note')}
                        error={errors.note}
                        className="sm:col-span-2"
                    >
                        <Input name="note" />
                    </Field>
                </div>
            )}
        </FormDialog>
    );
}

StudentsIndex.layout = {
    breadcrumbs: [{ title: 'Students', href: students.index() }],
};
