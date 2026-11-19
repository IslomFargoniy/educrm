import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarPlus,
    ChevronLeft,
    ChevronRight,
    Pencil,
    Tag,
} from 'lucide-react';
import { useState } from 'react';
import Field from '@/components/field';
import FormDialog from '@/components/form-dialog';
import GroupFormFields from '@/components/group-form-fields';
import type { GroupFormOptions } from '@/components/group-form-fields';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import MoneyInput from '@/components/money-input';
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
import {
    formatDate,
    formatDays,
    formatMonth,
    shiftMonth,
    today,
} from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import groups from '@/routes/groups';
import groupLessons from '@/routes/groups/lessons';
import groupPrices from '@/routes/groups/prices';
import lessons from '@/routes/lessons';
import enrollmentRoutes from '@/routes/enrollments';
import students from '@/routes/students';

type Props = GroupFormOptions & {
    group: {
        id: number;
        name: string;
        subject: string | null;
        days: number[];
        teacher_percent: number;
        status: string;
        teacher_id: number | null;
        room_id: number | null;
        branch_id: number;
        start_time: string;
        end_time: string;
        start_date: string;
        end_date: string | null;
        teacher: string | null;
        room: string | null;
        branch: string;
        prices: {
            id: number;
            monthly_price: number;
            lessons_per_month: number;
            effective_from: string;
        }[];
        current_price: {
            monthly_price: number;
            lessons_per_month: number;
        } | null;
    };
    students: {
        enrollment_id: number;
        student_id: number;
        full_name: string;
        phone: string | null;
        status: string;
        balance: number | null;
        is_debtor: boolean | null;
        teacher_percent: number | null;
        discount_type: string | null;
        discount_value: number | null;
        frozen: boolean;
        joined_at: string;
        left_at: string | null;
    }[];
    lessons: {
        id: number;
        date: string;
        start_time: string;
        status: string;
        teacher: string | null;
        is_substitute: boolean;
        is_makeup: boolean;
        cancel_reason: string | null;
        attendance: Record<string, string>;
    }[];
    month: string;
};

const marks: Record<string, { label: string; className: string }> = {
    present: { label: '✓', className: 'bg-success/15 text-success' },
    absent: { label: '✕', className: 'bg-danger/15 text-danger' },
    excused: {
        label: 'S',
        className: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    },
};

export default function GroupShow({
    group,
    students: roster,
    lessons: monthLessons,
    month,
    ...options
}: Props) {
    const t = useT();
    const locale = useLocale();
    const { can } = useCan();
    const [tab, setTab] = useState<'journal' | 'students' | 'prices'>(
        'journal',
    );
    const goMonth = (by: number) =>
        router.get(
            groups.show(group.id).url,
            { month: shiftMonth(month, by) },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <>
            <Head title={group.name} />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={
                        <span className="flex items-center gap-2">
                            {group.name}{' '}
                            {group.status !== 'active' && (
                                <StatusBadge status={group.status} />
                            )}
                        </span>
                    }
                    description={
                        <span className="flex flex-wrap gap-x-4 gap-y-1">
                            <span>{group.subject}</span>
                            <span>{group.teacher ?? '—'}</span>
                            <span>
                                {formatDays(group.days, locale)} ·{' '}
                                {group.start_time}–{group.end_time}
                            </span>
                            {group.room && <span>{group.room}</span>}
                            <span>{group.branch}</span>
                        </span>
                    }
                    actions={
                        <>
                            {can('lessons.manage') && (
                                <FormDialog
                                    title={t('Extra lesson')}
                                    description={t(
                                        'A make-up lesson is billed like a normal lesson (within the monthly limit).',
                                    )}
                                    form={groupLessons.store.form(group.id)}
                                    trigger={
                                        <Button variant="outline">
                                            <CalendarPlus />
                                            {t('Extra lesson')}
                                        </Button>
                                    }
                                >
                                    {(errors) => (
                                        <>
                                            <Field
                                                label={t('Date')}
                                                error={errors.date}
                                            >
                                                <Input
                                                    name="date"
                                                    type="date"
                                                    defaultValue={today()}
                                                    required
                                                />
                                            </Field>
                                            <div className="grid grid-cols-2 gap-3">
                                                <Field
                                                    label={t('Starts at')}
                                                    error={errors.start_time}
                                                >
                                                    <Input
                                                        name="start_time"
                                                        type="time"
                                                        defaultValue={
                                                            group.start_time
                                                        }
                                                        required
                                                    />
                                                </Field>
                                                <Field
                                                    label={t('Ends at')}
                                                    error={errors.end_time}
                                                >
                                                    <Input
                                                        name="end_time"
                                                        type="time"
                                                        defaultValue={
                                                            group.end_time
                                                        }
                                                        required
                                                    />
                                                </Field>
                                            </div>
                                            <Field
                                                label={t('Teacher')}
                                                error={errors.teacher_id}
                                            >
                                                <NativeSelect
                                                    name="teacher_id"
                                                    defaultValue={
                                                        group.teacher_id ?? ''
                                                    }
                                                >
                                                    {options.teachers.map(
                                                        (teacher) => (
                                                            <option
                                                                key={teacher.id}
                                                                value={
                                                                    teacher.id
                                                                }
                                                            >
                                                                {teacher.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                            </Field>
                                            <Field
                                                label={t('Room')}
                                                error={errors.room_id}
                                            >
                                                <NativeSelect
                                                    name="room_id"
                                                    defaultValue={
                                                        group.room_id ?? ''
                                                    }
                                                >
                                                    <option value="">—</option>
                                                    {options.rooms.map(
                                                        (room) => (
                                                            <option
                                                                key={room.id}
                                                                value={room.id}
                                                            >
                                                                {room.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                            </Field>
                                        </>
                                    )}
                                </FormDialog>
                            )}
                            {can('groups.prices') && (
                                <FormDialog
                                    title={t('New price')}
                                    description={t(
                                        'The new price applies from the first day of the chosen month.',
                                    )}
                                    form={groupPrices.store.form(group.id)}
                                    trigger={
                                        <Button variant="outline">
                                            <Tag />
                                            {t('Change price')}
                                        </Button>
                                    }
                                >
                                    {(errors) => (
                                        <>
                                            <Field
                                                label={t('Monthly price')}
                                                error={errors.monthly_price}
                                            >
                                                <MoneyInput
                                                    name="monthly_price"
                                                    min={0}
                                                    defaultValue={
                                                        group.current_price
                                                            ?.monthly_price
                                                    }
                                                    required
                                                />
                                            </Field>
                                            <Field
                                                label={t(
                                                    'Lessons per month (N)',
                                                )}
                                                error={errors.lessons_per_month}
                                            >
                                                <Input
                                                    name="lessons_per_month"
                                                    type="number"
                                                    min={1}
                                                    max={60}
                                                    defaultValue={
                                                        group.current_price
                                                            ?.lessons_per_month ??
                                                        12
                                                    }
                                                    required
                                                />
                                            </Field>
                                            <Field
                                                label={t('From month')}
                                                error={errors.effective_from}
                                            >
                                                <Input
                                                    name="effective_from"
                                                    type="month"
                                                    defaultValue={shiftMonth(
                                                        today().slice(0, 7),
                                                        1,
                                                    )}
                                                    required
                                                />
                                            </Field>
                                        </>
                                    )}
                                </FormDialog>
                            )}
                            {can('groups.manage') && (
                                <FormDialog
                                    wide
                                    title={t('Edit group')}
                                    form={groups.update.form(group.id)}
                                    trigger={
                                        <Button variant="outline">
                                            <Pencil />
                                            {t('Edit')}
                                        </Button>
                                    }
                                >
                                    {(errors) => (
                                        <GroupFormFields
                                            errors={errors}
                                            options={options}
                                            values={group}
                                            creating={false}
                                        />
                                    )}
                                </FormDialog>
                            )}
                        </>
                    }
                />

                <div className="grid grid-cols-2 gap-2 md:grid-cols-4">
                    <Card label={t('Monthly price')}>
                        <Money
                            value={group.current_price?.monthly_price}
                            className="text-xl font-extrabold"
                        />
                    </Card>
                    <Card label={t('Lessons per month')}>
                        <span className="text-xl font-extrabold">
                            {group.current_price?.lessons_per_month ?? '—'}
                        </span>
                    </Card>
                    <Card label={t('Teacher percent')}>
                        <span className="text-xl font-extrabold">
                            {group.teacher_percent}%
                        </span>
                    </Card>
                    <Card label={t('Students')}>
                        <span className="text-xl font-extrabold">
                            {
                                roster.filter((s) =>
                                    ['active', 'trial'].includes(s.status),
                                ).length
                            }
                        </span>
                    </Card>
                </div>

                <div className="flex gap-1 border-b">
                    {(['journal', 'students', 'prices'] as const).map((key) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setTab(key)}
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 text-sm font-semibold',
                                tab === key
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground',
                            )}
                        >
                            {t(
                                {
                                    journal: 'Attendance journal',
                                    students: 'Students',
                                    prices: 'Price history',
                                }[key],
                            )}
                        </button>
                    ))}
                </div>

                {tab === 'journal' && (
                    <section className="space-y-3">
                        <div className="flex items-center gap-2">
                            <Button
                                size="icon"
                                variant="outline"
                                onClick={() => goMonth(-1)}
                            >
                                <ChevronLeft />
                            </Button>
                            <span className="min-w-36 text-center font-bold">
                                {formatMonth(month, locale)}
                            </span>
                            <Button
                                size="icon"
                                variant="outline"
                                onClick={() => goMonth(1)}
                            >
                                <ChevronRight />
                            </Button>
                        </div>
                        <div className="overflow-x-auto rounded-xl border bg-card">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="sticky left-0 z-10 min-w-44 bg-card px-3 py-2 text-left text-xs text-muted-foreground uppercase">
                                            {t('Student')}
                                        </th>
                                        {monthLessons.map((lesson) => (
                                            <th
                                                key={lesson.id}
                                                className="px-1 py-2 text-center"
                                            >
                                                <Link
                                                    href={lessons.show(
                                                        lesson.id,
                                                    )}
                                                    className={cn(
                                                        'inline-flex flex-col items-center rounded-md px-1.5 py-1 text-xs hover:bg-muted',
                                                        lesson.status ===
                                                            'cancelled' &&
                                                            'line-through opacity-50',
                                                        lesson.is_substitute &&
                                                            'text-highlight',
                                                        lesson.date ===
                                                            today() &&
                                                            'ring-2 ring-primary',
                                                    )}
                                                    title={[
                                                        lesson.teacher,
                                                        lesson.cancel_reason,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                >
                                                    <span className="font-bold">
                                                        {lesson.date.slice(
                                                            8,
                                                            10,
                                                        )}
                                                    </span>
                                                    <span className="text-[10px] text-muted-foreground">
                                                        {lesson.is_makeup
                                                            ? '+'
                                                            : lesson.start_time}
                                                    </span>
                                                </Link>
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {roster.map((s, index) => (
                                        <tr
                                            key={s.enrollment_id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="sticky left-0 z-10 bg-card px-3 py-1.5 font-medium whitespace-nowrap">
                                                <span className="mr-2 inline-block w-5 text-right text-xs text-muted-foreground tabular-nums">
                                                    {index + 1}
                                                </span>
                                                {s.full_name}
                                                {s.status === 'trial' && (
                                                    <span className="ml-1 text-xs text-highlight">
                                                        ({t('trial')})
                                                    </span>
                                                )}
                                            </td>
                                            {monthLessons.map((lesson) => {
                                                const mark =
                                                    marks[
                                                        lesson.attendance[
                                                            s.enrollment_id
                                                        ] ?? ''
                                                    ];

                                                return (
                                                    <td
                                                        key={lesson.id}
                                                        className="px-1 py-1.5 text-center"
                                                    >
                                                        {mark ? (
                                                            <span
                                                                className={cn(
                                                                    'inline-flex size-6 items-center justify-center rounded-md text-xs font-bold',
                                                                    mark.className,
                                                                )}
                                                            >
                                                                {mark.label}
                                                            </span>
                                                        ) : (
                                                            <span className="text-muted-foreground/40">
                                                                ·
                                                            </span>
                                                        )}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            ✓ {t('present')} · ✕ {t('absent without reason')} ·
                            S {t('excused')} ·{' '}
                            <span className="text-highlight">
                                {t('orange — substitute teacher')}
                            </span>
                        </p>
                    </section>
                )}

                {tab === 'students' && (
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12 text-center">
                                        №
                                    </TableHead>
                                    <TableHead>{t('Student')}</TableHead>
                                    <TableHead>{t('Phone')}</TableHead>
                                    <TableHead>{t('Status')}</TableHead>
                                    <TableHead>{t('Joined')}</TableHead>
                                    {can('groups.prices') && (
                                        <TableHead>{t('Percent')}</TableHead>
                                    )}
                                    <TableHead className="text-right">
                                        {t('Balance')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {roster.map((s, index) => (
                                    <TableRow key={s.enrollment_id}>
                                        <TableCell className="w-12 text-center text-muted-foreground tabular-nums">
                                            {index + 1}
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            {can('students.view') ? (
                                                <Link
                                                    href={students.show(
                                                        s.student_id,
                                                    )}
                                                    className="hover:text-primary"
                                                >
                                                    {s.full_name}
                                                </Link>
                                            ) : (
                                                s.full_name
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {s.phone ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={
                                                    s.frozen
                                                        ? 'frozen'
                                                        : s.status
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {formatDate(s.joined_at)}
                                            {s.left_at &&
                                                ` — ${formatDate(s.left_at)}`}
                                        </TableCell>
                                        {can('groups.prices') && (
                                            <TableCell>
                                                <div className="flex items-center gap-1">
                                                    {s.teacher_percent !==
                                                    null ? (
                                                        <span className="font-semibold">
                                                            {s.teacher_percent}%
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            {t(':p% (group)', {
                                                                p: group.teacher_percent,
                                                            })}
                                                        </span>
                                                    )}
                                                    {can(
                                                        'enrollments.manage',
                                                    ) &&
                                                        !s.left_at && (
                                                            <StudentPercentDialog
                                                                student={s}
                                                                groupPercent={
                                                                    group.teacher_percent
                                                                }
                                                            />
                                                        )}
                                                </div>
                                            </TableCell>
                                        )}
                                        <TableCell className="text-right">
                                            {s.balance !== null ? (
                                                <Money
                                                    value={s.balance}
                                                    signed
                                                />
                                            ) : s.is_debtor ? (
                                                <span className="font-semibold text-danger">
                                                    {t('debtor')}
                                                </span>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {tab === 'prices' && (
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12 text-center">
                                        №
                                    </TableHead>
                                    <TableHead>{t('From month')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Monthly price')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Lessons per month')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Lesson price')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {group.prices.map((p, index) => (
                                    <TableRow key={p.id}>
                                        <TableCell className="w-12 text-center text-muted-foreground tabular-nums">
                                            {index + 1}
                                        </TableCell>
                                        <TableCell>
                                            {formatMonth(
                                                p.effective_from,
                                                locale,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Money value={p.monthly_price} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {p.lessons_per_month}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Money
                                                value={
                                                    p.monthly_price /
                                                    p.lessons_per_month
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

function Card({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="rounded-xl border bg-card px-3 py-2">
            <div className="text-[11px] font-semibold text-muted-foreground uppercase">
                {label}
            </div>
            {children}
        </div>
    );
}

GroupShow.layout = {
    breadcrumbs: [{ title: 'Groups', href: groups.index() }],
};

/**
 * Teacher percent for one student of the group. The student's discount is sent
 * unchanged, because the endpoint saves discount and percent together.
 */
function StudentPercentDialog({
    student,
    groupPercent,
}: {
    student: {
        enrollment_id: number;
        full_name: string;
        teacher_percent: number | null;
        discount_type: string | null;
        discount_value: number | null;
    };
    groupPercent: number;
}) {
    const t = useT();

    return (
        <FormDialog
            title={t('Teacher percent for this student')}
            description={student.full_name}
            form={enrollmentRoutes.update.form(student.enrollment_id)}
            trigger={
                <Button
                    size="icon"
                    variant="ghost"
                    className="size-7"
                    aria-label={t('Teacher percent for this student')}
                >
                    <Pencil className="size-3.5" />
                </Button>
            }
        >
            {(errors) => (
                <>
                    <input
                        type="hidden"
                        name="discount_type"
                        value={student.discount_type ?? ''}
                    />
                    <input
                        type="hidden"
                        name="discount_value"
                        value={student.discount_value ?? 0}
                    />
                    <Field
                        label={t('Teacher percent')}
                        error={errors.teacher_percent}
                        hint={t('Empty — group percent (:p%)', {
                            p: groupPercent,
                        })}
                    >
                        <Input
                            name="teacher_percent"
                            type="number"
                            min={0}
                            max={100}
                            defaultValue={student.teacher_percent ?? ''}
                            autoFocus
                        />
                    </Field>
                </>
            )}
        </FormDialog>
    );
}
