import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import CallStatusTag, {
    CALL_STATUS_COLORS,
} from '@/components/call-status-tag';
import EmptyState from '@/components/empty-state';
import Field from '@/components/field';
import NameFields from '@/components/name-fields';
import FormDialog from '@/components/form-dialog';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PhoneInput from '@/components/phone-input';
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
import { formatDate, formatPhone, today } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useT } from '@/lib/i18n';
import students from '@/routes/students';

type Student = {
    id: number;
    full_name: string;
    phone: string | null;
    groups: { name: string; status: string }[];
    balance: number;
    call_status: string | null;
    call_status_at: string | null;
    left: { left_at: string | null; leave_reason: string | null } | null;
};

type Category = 'new' | 'active' | 'debtors' | 'archive';

const CATEGORY_TITLES: Record<Category, string> = {
    new: 'New students',
    active: 'Active students',
    debtors: 'Debtors',
    archive: 'Archived students',
};

const CATEGORY_HINTS: Record<Category, string> = {
    new: 'Came to two lessons or more and have not paid yet.',
    active: 'Studying and have paid.',
    debtors: 'Owe money in a group.',
    archive: 'No group any more: graduated, left or removed.',
};

type Props = {
    students: Paginated<Student>;
    filters: {
        q?: string;
        group_id?: string;
        status?: string;
        category?: Category;
        teacher_id?: string;
        course_id?: string;
        call_status?: string;
    };
    groups: { id: number; name: string }[];
    counts: Record<Category, number>;
    debtTotals: { debt: number; credit: number } | null;
    teachers: { id: number; name: string }[];
    courses: { id: number; name: string }[];
    callStatuses: string[];
};

export default function StudentsIndex({
    students: list,
    filters,
    groups,
    counts,
    debtTotals,
    teachers,
    courses,
    callStatuses,
}: Props) {
    const category = filters.category;
    const debtorsView = category === 'debtors';
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
                    title={
                        category ? t(CATEGORY_TITLES[category]) : t('Students')
                    }
                    description={`${t(':count students', { count: list.total })}${category ? ' · ' + t(CATEGORY_HINTS[category]) : ''}`}
                    actions={
                        can('students.create') && (
                            <NewStudentDialog groups={groups} />
                        )
                    }
                />

                <div className="flex flex-wrap gap-1.5">
                    {(
                        [undefined, 'new', 'active', 'debtors', 'archive'] as (
                            | Category
                            | undefined
                        )[]
                    ).map((key) => (
                        <button
                            key={key ?? 'all'}
                            type="button"
                            onClick={() =>
                                router.get(
                                    students.index().url,
                                    key ? { category: key } : {},
                                    { preserveScroll: true },
                                )
                            }
                            className={cn(
                                'rounded-full border px-3 py-1 text-xs font-semibold',
                                category === key
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {key ? t(CATEGORY_TITLES[key]) : t('All students')}
                            {key && (
                                <span className="ml-1.5 tabular-nums opacity-80">
                                    {counts[key]}
                                </span>
                            )}
                        </button>
                    ))}
                </div>

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
                        className="w-48"
                        value={filters.teacher_id ?? ''}
                        onChange={(e) =>
                            apply({ teacher_id: e.target.value || undefined })
                        }
                        aria-label={t('Teacher')}
                    >
                        <option value="">{t('All teachers')}</option>
                        {teachers.map((o) => (
                            <option key={o.id} value={o.id}>
                                {o.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        className="w-44"
                        value={filters.course_id ?? ''}
                        onChange={(e) =>
                            apply({ course_id: e.target.value || undefined })
                        }
                        aria-label={t('Course')}
                    >
                        <option value="">{t('All courses')}</option>
                        {courses.map((o) => (
                            <option key={o.id} value={o.id}>
                                {o.name}
                            </option>
                        ))}
                    </NativeSelect>
                    {debtorsView && (
                        <NativeSelect
                            className="w-56"
                            value={filters.call_status ?? ''}
                            onChange={(e) =>
                                apply({
                                    call_status: e.target.value || undefined,
                                })
                            }
                            aria-label={t('Call result')}
                        >
                            <option value="">{t('Any call result')}</option>
                            <option value="none">{t('No tag yet')}</option>
                            {callStatuses.map((value) => (
                                <option key={value} value={value}>
                                    {t(`call.${value}`)}
                                </option>
                            ))}
                        </NativeSelect>
                    )}
                    {!category && (
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
                    )}
                </div>

                {debtTotals && (
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                        <span>
                            {t('Owe')}:{' '}
                            <Money
                                value={-debtTotals.debt}
                                signed
                                className="font-bold"
                            />
                        </span>
                        <span>
                            {t('Paid ahead')}:{' '}
                            <Money
                                value={debtTotals.credit}
                                signed
                                className="font-bold"
                            />
                        </span>
                        <span className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            {callStatuses.map((value) => (
                                <span
                                    key={value}
                                    className="flex items-center gap-1"
                                >
                                    <span
                                        className={cn(
                                            'size-2.5 rounded-full',
                                            CALL_STATUS_COLORS[value],
                                        )}
                                    />
                                    {t(`call.${value}`)}
                                </span>
                            ))}
                        </span>
                    </div>
                )}

                {list.data.length === 0 ? (
                    <EmptyState>{t('No students found.')}</EmptyState>
                ) : (
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12 text-center">
                                        №
                                    </TableHead>
                                    <TableHead>{t('Student')}</TableHead>
                                    <TableHead>{t('Phone')}</TableHead>
                                    <TableHead>{t('Groups')}</TableHead>
                                    {category === 'archive' && (
                                        <TableHead>{t('Left')}</TableHead>
                                    )}
                                    <TableHead className="text-right">
                                        {t('Balance')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {list.data.map((s, index) => (
                                    <TableRow
                                        key={s.id}
                                        className="cursor-pointer"
                                        onClick={() =>
                                            router.visit(
                                                students.show(s.id).url,
                                            )
                                        }
                                    >
                                        <TableCell className="w-12 text-center text-muted-foreground tabular-nums">
                                            {(list.from ?? 1) + index}
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            <Link
                                                href={students.show(s.id)}
                                                className="hover:text-primary"
                                            >
                                                {s.full_name}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {debtorsView ? (
                                                <CallStatusTag
                                                    studentId={s.id}
                                                    phone={s.phone}
                                                    status={s.call_status}
                                                    at={s.call_status_at}
                                                    statuses={callStatuses}
                                                />
                                            ) : s.phone ? (
                                                formatPhone(s.phone)
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {s.groups
                                                .map((g) => g.name)
                                                .join(', ') || '—'}
                                        </TableCell>
                                        {category === 'archive' && (
                                            <TableCell className="text-sm text-muted-foreground">
                                                {s.left?.left_at
                                                    ? formatDate(s.left.left_at)
                                                    : '—'}
                                                {s.left?.leave_reason &&
                                                    ` · ${t(`leave.${s.left.leave_reason}`)}`}
                                            </TableCell>
                                        )}
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
                    <NameFields errors={errors} autoFocus />
                    <Field label={t('Phone')} error={errors.phone}>
                        <PhoneInput name="phone" />
                    </Field>
                    <Field label={t('Birth date')} error={errors.birth_date}>
                        <Input name="birth_date" type="date" />
                    </Field>
                    <NameFields
                        errors={errors}
                        prefix="guardian_"
                        required={false}
                        firstLabel={t('Parent first name')}
                        lastLabel={t('Parent last name')}
                    />
                    <Field
                        label={t('Parent phone')}
                        error={errors.guardian_phone}
                        hint={t('A parent account is created with this phone.')}
                    >
                        <PhoneInput name="guardian_phone" />
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
