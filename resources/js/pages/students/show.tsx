import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Banknote,
    KeyRound,
    LogOut,
    Pencil,
    Percent,
    Plus,
    Printer,
    Shuffle,
    Snowflake,
    Trash2,
    Undo2,
    UserPlus,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import Field from '@/components/field';
import NameFields from '@/components/name-fields';
import FormDialog from '@/components/form-dialog';
import HistoryTable from '@/components/history-table';
import type { History } from '@/components/history-table';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import PeriodFilter from '@/components/period-filter';
import StatusBadge from '@/components/status-badge';
import StudentFace from '@/components/student-face';
import type { StudentFaceProps, StudentVisit } from '@/components/student-face';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import MoneyInput from '@/components/money-input';
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
import {
    formatDate,
    formatDateTime,
    formatMoney,
    formatMonth,
    currentMonth,
    today,
    formatPhone,
} from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import enrollmentRoutes from '@/routes/enrollments';
import enrollmentPayments from '@/routes/enrollments/payments';
import groupRoutes from '@/routes/groups';
import paymentRoutes from '@/routes/payments';
import studentRoutes from '@/routes/students';
import guardianRoutes from '@/routes/students/guardians';

type Enrollment = {
    id: number;
    group: {
        id: number;
        name: string;
        branch: string;
        branch_id: number;
        teacher: string | null;
    };
    status: string;
    joined_at: string;
    left_at: string | null;
    leave_reason: 'graduated' | 'dropped' | 'expelled' | null;
    leave_note: string | null;
    balance: number;
    available: number;
    monthly_price: number | null;
    discounted_price: number | null;
    lessons_per_month: number;
    billed_this_month: number;
    month_limit: number;
    discount_type: 'percent' | 'amount' | null;
    discount_value: number;
    teacher_percent: number | null;
    group_percent: number;
    trial_lessons: number;
    trial_marked: number;
    freezes: {
        id: number;
        from: string;
        to: string;
        reason: string | null;
        active: boolean;
    }[];
    transferred_from: string | null;
    history: History;
};

type Payment = {
    id: number;
    group: string;
    amount: number;
    paid_at: string;
    cashbox: string;
    receipt_no: string;
    received_by: string | null;
    reversed: boolean;
    reverse_reason: string | null;
    can_reverse: boolean;
};

type Props = {
    student: {
        id: number;
        full_name: string;
        first_name: string | null;
        last_name: string | null;
        phone: string | null;
        birth_date: string | null;
        note: string | null;
        guardians: {
            id: number;
            name: string;
            phone: string | null;
            relation: string;
            telegram: boolean;
        }[];
    };
    enrollments: Enrollment[];
    payments: Payment[];
    face: StudentFaceProps;
    visits: StudentVisit[];
    period: { from: string; to: string };
    groups: { id: number; name: string; branch_id: number }[];
    cashboxes: {
        id: number;
        name: string;
        branch_id: number;
        method: string;
    }[];
};

export default function StudentShow({
    student,
    enrollments,
    payments,
    face,
    visits,
    period,
    groups,
    cashboxes,
}: Props) {
    const t = useT();
    const { can } = useCan();
    const [selectedId, setSelectedId] = useState<number | null>(
        enrollments[0]?.id ?? null,
    );
    const selected =
        enrollments.find((e) => e.id === selectedId) ?? enrollments[0];

    return (
        <>
            <Head title={student.full_name} />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={student.full_name}
                    description={
                        <span className="flex flex-wrap gap-x-4 gap-y-1">
                            {student.phone && (
                                <span>{formatPhone(student.phone)}</span>
                            )}
                            {student.birth_date && (
                                <span>
                                    {t('Born')}:{' '}
                                    {formatDate(student.birth_date)}
                                </span>
                            )}
                            {student.note && <span>{student.note}</span>}
                        </span>
                    }
                    actions={
                        <>
                            {can('students.update') && (
                                <EditStudentDialog student={student} />
                            )}
                            {can('enrollments.manage') && (
                                <EnrollDialog
                                    studentId={student.id}
                                    groups={groups}
                                />
                            )}
                        </>
                    }
                />

                <section className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="font-semibold text-muted-foreground">
                        {t('Parents')}:
                    </span>
                    {student.guardians.length === 0 && (
                        <span className="text-muted-foreground">—</span>
                    )}
                    {student.guardians.map((g) => (
                        <Badge
                            key={g.id}
                            variant="outline"
                            className="gap-1.5 py-1"
                        >
                            {g.name}
                            {g.phone && (
                                <span className="text-muted-foreground">
                                    {formatPhone(g.phone)}
                                </span>
                            )}
                            {g.telegram && (
                                <span title="Telegram" className="text-sky-600">
                                    ✈
                                </span>
                            )}
                            {can('students.update') && (
                                <button
                                    type="button"
                                    title={t('Reset password')}
                                    className="text-muted-foreground hover:text-foreground"
                                    onClick={() =>
                                        confirm(
                                            t(
                                                'Give :name a new password? The old one will stop working.',
                                                { name: g.name },
                                            ),
                                        ) &&
                                        router.post(
                                            guardianRoutes.password({
                                                student: student.id,
                                                guardian: g.id,
                                            }).url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <KeyRound className="size-3" />
                                </button>
                            )}
                            {can('students.update') && (
                                <button
                                    type="button"
                                    className="text-muted-foreground hover:text-danger"
                                    onClick={() =>
                                        confirm(t('Remove this parent?')) &&
                                        router.delete(
                                            guardianRoutes.destroy({
                                                student: student.id,
                                                guardian: g.id,
                                            }).url,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Trash2 className="size-3" />
                                </button>
                            )}
                        </Badge>
                    ))}
                    {can('students.update') && (
                        <GuardianDialog studentId={student.id} />
                    )}
                </section>

                <StudentFace
                    studentId={student.id}
                    face={face}
                    visits={visits}
                />

                {enrollments.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        {t('The student is not in any group yet.')}
                    </div>
                ) : (
                    <>
                        <div className="flex flex-wrap gap-2">
                            {enrollments.map((e) => (
                                <button
                                    key={e.id}
                                    type="button"
                                    onClick={() => setSelectedId(e.id)}
                                    className={cn(
                                        'flex items-center gap-2 rounded-xl border px-3 py-2 text-left text-sm transition-colors',
                                        selected?.id === e.id
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'bg-card hover:border-primary/50',
                                    )}
                                >
                                    <span className="font-bold">
                                        {e.group.name}
                                    </span>
                                    <Money
                                        value={e.balance}
                                        className={cn(
                                            'text-xs font-semibold',
                                            selected?.id !== e.id &&
                                                (e.balance < 0
                                                    ? 'text-danger'
                                                    : e.balance > 0
                                                      ? 'text-success'
                                                      : ''),
                                        )}
                                    />
                                    {e.status !== 'active' && (
                                        <StatusBadge
                                            status={
                                                e.status === 'left' &&
                                                e.leave_reason === 'graduated'
                                                    ? 'graduated'
                                                    : e.status
                                            }
                                        />
                                    )}
                                </button>
                            ))}
                        </div>

                        {selected && (
                            <EnrollmentPanel
                                key={selected.id}
                                enrollment={selected}
                                studentName={student.full_name}
                                others={enrollments.filter(
                                    (e) =>
                                        e.id !== selected.id &&
                                        ['active', 'trial'].includes(e.status),
                                )}
                                period={period}
                                groups={groups.filter(
                                    (g) => g.id !== selected.group.id,
                                )}
                                cashboxes={cashboxes.filter(
                                    (c) =>
                                        c.branch_id ===
                                        selected.group.branch_id,
                                )}
                            />
                        )}
                    </>
                )}

                {can('students.view_payments') && payments.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="text-lg font-bold">{t('Payments')}</h2>
                        <div className="overflow-hidden rounded-xl border bg-card">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12 text-center">
                                            №
                                        </TableHead>
                                        <TableHead>{t('Date')}</TableHead>
                                        <TableHead>{t('Group')}</TableHead>
                                        <TableHead>{t('Cashbox')}</TableHead>
                                        <TableHead>{t('Receipt')}</TableHead>
                                        <TableHead className="text-right">
                                            {t('Amount')}
                                        </TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payments.map((p, index) => (
                                        <TableRow
                                            key={p.id}
                                            className={cn(
                                                p.reversed && 'opacity-60',
                                            )}
                                        >
                                            <TableCell className="w-12 text-center text-muted-foreground tabular-nums">
                                                {index + 1}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {formatDateTime(p.paid_at)}
                                            </TableCell>
                                            <TableCell>{p.group}</TableCell>
                                            <TableCell>{p.cashbox}</TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {p.receipt_no}
                                                {p.reversed && (
                                                    <div className="text-danger">
                                                        {t('Cancelled')}:{' '}
                                                        {p.reverse_reason}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right font-semibold">
                                                <Money value={p.amount} />
                                            </TableCell>
                                            <TableCell className="text-right whitespace-nowrap">
                                                {!p.reversed &&
                                                    can('payments.create') && (
                                                        <>
                                                            <Button
                                                                asChild
                                                                size="icon"
                                                                variant="ghost"
                                                                title={t(
                                                                    'Print receipt (80 mm)',
                                                                )}
                                                            >
                                                                <a
                                                                    href={
                                                                        paymentRoutes.receipt(
                                                                            p.id,
                                                                            {
                                                                                query: {
                                                                                    format: 'thermal',
                                                                                },
                                                                            },
                                                                        ).url
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    <Printer />
                                                                </a>
                                                            </Button>
                                                            <Button
                                                                asChild
                                                                size="sm"
                                                                variant="ghost"
                                                            >
                                                                <a
                                                                    href={
                                                                        paymentRoutes.receipt(
                                                                            p.id,
                                                                            {
                                                                                query: {
                                                                                    format: 'a4',
                                                                                },
                                                                            },
                                                                        ).url
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    A4
                                                                </a>
                                                            </Button>
                                                        </>
                                                    )}
                                                {p.can_reverse && (
                                                    <ReversePaymentDialog
                                                        payment={p}
                                                    />
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

function EnrollmentPanel({
    enrollment: e,
    studentName,
    others,
    period,
    groups,
    cashboxes,
}: {
    enrollment: Enrollment;
    studentName: string;
    others: Enrollment[];
    period: Props['period'];
    groups: Props['groups'];
    cashboxes: Props['cashboxes'];
}) {
    const t = useT();
    const locale = useLocale();
    const { can } = useCan();
    const studying = ['active', 'trial'].includes(e.status);
    const activeFreeze = e.freezes.find((f) => f.active);

    return (
        <section className="space-y-4 rounded-2xl border bg-card p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <Link
                        href={groupRoutes.show(e.group.id)}
                        className="text-lg font-bold hover:text-primary"
                    >
                        {e.group.name}
                    </Link>
                    <div className="text-sm text-muted-foreground">
                        {e.group.branch} · {e.group.teacher ?? '—'} ·{' '}
                        {t('since :date', { date: formatDate(e.joined_at) })}
                        {e.left_at &&
                            ` · ${t('until :date', { date: formatDate(e.left_at) })}`}
                        {e.leave_reason &&
                            ` · ${t(`leave.${e.leave_reason}`)}${e.leave_note ? ` (${e.leave_note})` : ''}`}
                        {e.transferred_from &&
                            ` · ${t('moved from :group', { group: e.transferred_from })}`}
                    </div>
                </div>
                <StatusBadge
                    status={
                        activeFreeze
                            ? 'frozen'
                            : e.status === 'left' &&
                                e.leave_reason === 'graduated'
                              ? 'graduated'
                              : e.status
                    }
                />
            </div>

            <div className="grid grid-cols-2 gap-2 md:grid-cols-5">
                <Info label={t('Balance')}>
                    <Money value={e.balance} signed className="text-xl" />
                    <div className="text-xs text-muted-foreground">
                        {e.balance < 0
                            ? t('debt')
                            : e.balance > 0
                              ? t('paid in advance')
                              : ''}
                    </div>
                </Info>
                <Info label={formatMonth(currentMonth(), locale)}>
                    <span className="text-xl font-extrabold">
                        {e.billed_this_month} / {e.month_limit}
                    </span>
                    <div className="text-xs text-muted-foreground">
                        {t('lessons billed')}
                    </div>
                </Info>
                <Info label={t('Monthly price')}>
                    <Money
                        value={e.discounted_price}
                        className="text-xl font-extrabold"
                    />
                    {e.discount_type &&
                        e.monthly_price !== e.discounted_price && (
                            <div className="text-xs text-muted-foreground line-through">
                                {formatMoney(e.monthly_price)}
                            </div>
                        )}
                </Info>
                <Info label={t('Lesson price')}>
                    <Money
                        value={
                            e.discounted_price && e.lessons_per_month
                                ? e.discounted_price / e.lessons_per_month
                                : null
                        }
                        className="text-xl font-extrabold"
                    />
                    <div className="text-xs text-muted-foreground">
                        {t(':n lessons per month', { n: e.lessons_per_month })}
                    </div>
                </Info>
                <Info label={t('Teacher percent')}>
                    <span className="text-xl font-extrabold">
                        {e.teacher_percent ?? e.group_percent}%
                    </span>
                    <div className="text-xs text-muted-foreground">
                        {e.teacher_percent === null
                            ? t('group default')
                            : t('personal')}
                    </div>
                </Info>
            </div>

            {e.status === 'trial' && (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-highlight/50 bg-highlight/10 p-3 text-sm">
                    <span>
                        {t(
                            'Trial: :marked of :total trial lessons marked. Trial lessons are billed only if the student continues.',
                            { marked: e.trial_marked, total: e.trial_lessons },
                        )}
                    </span>
                    {can('enrollments.manage') && (
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        enrollmentRoutes.trial(e.id).url,
                                        { continues: true },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('Continues')}
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    confirm(
                                        t(
                                            'Finish the trial? Trial lessons will not be billed.',
                                        ),
                                    ) &&
                                    router.post(
                                        enrollmentRoutes.trial(e.id).url,
                                        { continues: false },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('Does not continue')}
                            </Button>
                        </div>
                    )}
                </div>
            )}

            {e.freezes.length > 0 && (
                <div className="flex flex-wrap gap-2 text-xs">
                    {e.freezes.map((f) => (
                        <Badge
                            key={f.id}
                            variant="outline"
                            className={cn(
                                'gap-1',
                                f.active &&
                                    'border-sky-500 text-sky-700 dark:text-sky-300',
                            )}
                        >
                            <Snowflake className="size-3" />
                            {formatDate(f.from)} — {formatDate(f.to)}{' '}
                            {f.reason && `· ${f.reason}`}
                            {f.active && can('enrollments.manage') && (
                                <button
                                    type="button"
                                    className="ml-1 underline"
                                    onClick={() =>
                                        router.delete(
                                            enrollmentRoutes.unfreeze({
                                                enrollment: e.id,
                                                freeze: f.id,
                                            }).url,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('Unfreeze')}
                                </button>
                            )}
                        </Badge>
                    ))}
                </div>
            )}

            <div className="flex flex-wrap gap-2">
                {can('payments.create') && (
                    <FormDialog
                        title={t('Receive payment')}
                        description={`${e.group.name} · ${t('Balance')}: ${formatMoney(e.balance)}`}
                        form={enrollmentPayments.store.form(e.id)}
                        submitLabel={t('Receive')}
                        transform={(data) => ({
                            ...data,
                            idempotency_key: crypto.randomUUID(),
                        })}
                        trigger={
                            <Button className="bg-highlight text-highlight-foreground hover:bg-highlight/90">
                                <Banknote />
                                {t('Receive payment')}
                            </Button>
                        }
                    >
                        {(errors) => (
                            <>
                                <Field
                                    label={t('Amount')}
                                    error={errors.amount}
                                >
                                    <MoneyInput
                                        name="amount"
                                        min={1}
                                        required
                                        autoFocus
                                        defaultValue={
                                            e.balance < 0
                                                ? -e.balance
                                                : (e.discounted_price ??
                                                  undefined)
                                        }
                                    />
                                </Field>
                                <CashboxSelect
                                    cashboxes={cashboxes}
                                    error={errors.cashbox_id}
                                />
                                <Field label={t('Note')} error={errors.note}>
                                    <Input name="note" />
                                </Field>
                            </>
                        )}
                    </FormDialog>
                )}
                {can('enrollments.manage') && studying && (
                    <FormDialog
                        title={t('Move to another group')}
                        description={t(
                            'Unused money moves too; this month the lesson limit is shared between both groups.',
                        )}
                        form={enrollmentRoutes.transfer.form(e.id)}
                        trigger={
                            <Button variant="outline">
                                <Shuffle />
                                {t('Move to group')}
                            </Button>
                        }
                    >
                        {(errors) => (
                            <>
                                <Field
                                    label={t('New group')}
                                    error={errors.group_id}
                                >
                                    <NativeSelect name="group_id" required>
                                        {groups.map((g) => (
                                            <option key={g.id} value={g.id}>
                                                {g.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>
                                <Field
                                    label={t('From date')}
                                    error={errors.date}
                                >
                                    <Input
                                        name="date"
                                        type="date"
                                        defaultValue={today()}
                                        required
                                    />
                                </Field>
                            </>
                        )}
                    </FormDialog>
                )}
                {can('balance.transfer') &&
                    others.length > 0 &&
                    e.available > 0 && (
                        <FormDialog
                            title={t('Move balance to another group')}
                            description={t('Unused money: :amount', {
                                amount: formatMoney(e.available),
                            })}
                            form={enrollmentRoutes.transferBalance.form(e.id)}
                            trigger={
                                <Button variant="outline">
                                    <ArrowLeftRight />
                                    {t('Move balance')}
                                </Button>
                            }
                        >
                            {(errors) => (
                                <>
                                    <Field
                                        label={t('To group')}
                                        error={errors.to_enrollment_id}
                                    >
                                        <NativeSelect
                                            name="to_enrollment_id"
                                            required
                                        >
                                            {others.map((o) => (
                                                <option key={o.id} value={o.id}>
                                                    {o.group.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </Field>
                                    <Field
                                        label={t('Amount')}
                                        error={errors.amount}
                                    >
                                        <MoneyInput
                                            name="amount"
                                            min={1}
                                            max={e.available}
                                            defaultValue={e.available}
                                            required
                                        />
                                    </Field>
                                </>
                            )}
                        </FormDialog>
                    )}
                {can('enrollments.manage') && studying && (
                    <FormDialog
                        title={t('Freeze')}
                        description={t(
                            'Lessons are not billed and the student is hidden from attendance during this time.',
                        )}
                        form={enrollmentRoutes.freeze.form(e.id)}
                        trigger={
                            <Button variant="outline">
                                <Snowflake />
                                {t('Freeze')}
                            </Button>
                        }
                    >
                        {(errors) => (
                            <>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field
                                        label={t('From')}
                                        error={errors.from_date}
                                    >
                                        <Input
                                            name="from_date"
                                            type="date"
                                            defaultValue={today()}
                                            required
                                        />
                                    </Field>
                                    <Field
                                        label={t('To')}
                                        error={errors.to_date}
                                    >
                                        <Input
                                            name="to_date"
                                            type="date"
                                            required
                                        />
                                    </Field>
                                </div>
                                <Field
                                    label={t('Reason')}
                                    error={errors.reason}
                                >
                                    <Input name="reason" />
                                </Field>
                            </>
                        )}
                    </FormDialog>
                )}
                {can('enrollments.manage') && (
                    <FormDialog
                        title={t('Discount and percent')}
                        form={enrollmentRoutes.update.form(e.id)}
                        trigger={
                            <Button variant="outline">
                                <Percent />
                                {t('Discount / percent')}
                            </Button>
                        }
                    >
                        {(errors) => (
                            <>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field
                                        label={t('Discount type')}
                                        error={errors.discount_type}
                                    >
                                        <NativeSelect
                                            name="discount_type"
                                            defaultValue={e.discount_type ?? ''}
                                        >
                                            <option value="">
                                                {t('No discount')}
                                            </option>
                                            <option value="percent">%</option>
                                            <option value="amount">
                                                {t("so'm per month")}
                                            </option>
                                        </NativeSelect>
                                    </Field>
                                    <Field
                                        label={t('Discount')}
                                        error={errors.discount_value}
                                    >
                                        <MoneyInput
                                            name="discount_value"
                                            min={0}
                                            defaultValue={e.discount_value}
                                        />
                                    </Field>
                                </div>
                                {can('groups.prices') && (
                                    <Field
                                        label={t(
                                            'Teacher percent for this student',
                                        )}
                                        error={errors.teacher_percent}
                                        hint={t('Empty — group percent (:p%)', {
                                            p: e.group_percent,
                                        })}
                                    >
                                        <Input
                                            name="teacher_percent"
                                            type="number"
                                            min={0}
                                            max={100}
                                            step={1}
                                            defaultValue={
                                                e.teacher_percent ?? ''
                                            }
                                        />
                                    </Field>
                                )}
                            </>
                        )}
                    </FormDialog>
                )}
                {can('refunds.create') && e.available > 0 && (
                    <FormDialog
                        title={t('Return money')}
                        description={t('Unused money: :amount', {
                            amount: formatMoney(e.available),
                        })}
                        form={enrollmentRoutes.refund.form(e.id)}
                        trigger={
                            <Button variant="outline">
                                <Undo2 />
                                {t('Return money')}
                            </Button>
                        }
                    >
                        {(errors) => (
                            <>
                                <Field
                                    label={t('Amount')}
                                    error={errors.amount}
                                >
                                    <MoneyInput
                                        name="amount"
                                        min={1}
                                        max={e.available}
                                        defaultValue={e.available}
                                        required
                                    />
                                </Field>
                                <CashboxSelect
                                    cashboxes={cashboxes}
                                    error={errors.cashbox_id}
                                />
                                <Field label={t('Note')} error={errors.note}>
                                    <Input name="note" />
                                </Field>
                            </>
                        )}
                    </FormDialog>
                )}
                {can('debts.writeoff') && e.balance < 0 && (
                    <FormDialog
                        destructive
                        title={t('Write off debt')}
                        description={t(
                            'Debt :amount will be removed. The teacher gets nothing for these lessons.',
                            { amount: formatMoney(-e.balance) },
                        )}
                        form={enrollmentRoutes.writeOff.form(e.id)}
                        submitLabel={t('Write off')}
                        trigger={
                            <Button variant="outline">
                                <XCircle />
                                {t('Write off debt')}
                            </Button>
                        }
                    >
                        {(errors) => (
                            <Field label={t('Reason')} error={errors.reason}>
                                <Input name="reason" required />
                            </Field>
                        )}
                    </FormDialog>
                )}
                {can('enrollments.manage') && studying && (
                    <FormDialog
                        destructive
                        title={t('Graduate or remove from group')}
                        description={t(
                            'The debt (if any) stays in the debtor list.',
                        )}
                        form={enrollmentRoutes.leave.form(e.id)}
                        submitLabel={t('Remove')}
                        trigger={
                            <Button variant="ghost" className="text-danger">
                                <LogOut />
                                {t('Graduates / leaves')}
                            </Button>
                        }
                    >
                        {(errors) => (
                            <>
                                <Field
                                    label={t('Reason')}
                                    error={errors.reason}
                                >
                                    <NativeSelect
                                        name="reason"
                                        defaultValue="graduated"
                                        required
                                    >
                                        <option value="graduated">
                                            {t('leave.graduated')}
                                        </option>
                                        <option value="dropped">
                                            {t('leave.dropped')}
                                        </option>
                                        <option value="expelled">
                                            {t('leave.expelled')}
                                        </option>
                                    </NativeSelect>
                                </Field>
                                <Field
                                    label={t('Last day')}
                                    error={errors.date}
                                >
                                    <Input
                                        name="date"
                                        type="date"
                                        defaultValue={today()}
                                        required
                                    />
                                </Field>
                                <Field label={t('Note')} error={errors.note}>
                                    <Input name="note" maxLength={255} />
                                </Field>
                            </>
                        )}
                    </FormDialog>
                )}
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2 pt-2">
                <h3 className="font-bold">{t('Balance history')}</h3>
                <PeriodFilter from={period.from} to={period.to} />
            </div>
            <HistoryTable
                history={e.history}
                context="enrollment"
                exportName={`${studentName} ${e.group.name} ${period.from} ${period.to}`}
                labels={{
                    income: t('Total paid'),
                    expense: t('Total for lessons'),
                }}
            />
        </section>
    );
}

function Info({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="rounded-xl border bg-background px-3 py-2">
            <div className="text-[11px] font-semibold text-muted-foreground uppercase">
                {label}
            </div>
            {children}
        </div>
    );
}

function CashboxSelect({
    cashboxes,
    error,
}: {
    cashboxes: Props['cashboxes'];
    error?: string;
}) {
    const t = useT();

    return (
        <Field label={t('Cashbox')} error={error}>
            <NativeSelect name="cashbox_id" required>
                {cashboxes.map((c) => (
                    <option key={c.id} value={c.id}>
                        {c.name}
                    </option>
                ))}
            </NativeSelect>
        </Field>
    );
}

function EditStudentDialog({ student }: { student: Props['student'] }) {
    const t = useT();

    return (
        <FormDialog
            title={t('Edit student')}
            form={studentRoutes.update.form(student.id)}
            trigger={
                <Button variant="outline">
                    <Pencil />
                    {t('Edit')}
                </Button>
            }
        >
            {(errors) => (
                <>
                    <NameFields
                        errors={errors}
                        firstName={student.first_name}
                        lastName={student.last_name}
                    />
                    <Field label={t('Phone')} error={errors.phone}>
                        <PhoneInput
                            name="phone"
                            defaultValue={student.phone ?? ''}
                        />
                    </Field>
                    <Field label={t('Birth date')} error={errors.birth_date}>
                        <Input
                            name="birth_date"
                            type="date"
                            defaultValue={student.birth_date ?? ''}
                        />
                    </Field>
                    <Field label={t('Note')} error={errors.note}>
                        <Input name="note" defaultValue={student.note ?? ''} />
                    </Field>
                </>
            )}
        </FormDialog>
    );
}

function GuardianDialog({ studentId }: { studentId: number }) {
    const t = useT();

    return (
        <FormDialog
            title={t('Add parent')}
            description={t(
                'Parent logs in with this phone number and sees all children.',
            )}
            form={guardianRoutes.store.form(studentId)}
            trigger={
                <Button size="sm" variant="ghost">
                    <UserPlus />
                    {t('Add parent')}
                </Button>
            }
        >
            {(errors) => (
                <>
                    <NameFields errors={errors} />
                    <Field label={t('Phone')} error={errors.phone}>
                        <PhoneInput name="phone" required />
                    </Field>
                    <Field label={t('Relation')} error={errors.relation}>
                        <NativeSelect name="relation" defaultValue="mother">
                            <option value="mother">{t('Mother')}</option>
                            <option value="father">{t('Father')}</option>
                            <option value="parent">{t('Parent')}</option>
                            <option value="other">{t('Other')}</option>
                        </NativeSelect>
                    </Field>
                </>
            )}
        </FormDialog>
    );
}

function EnrollDialog({
    studentId,
    groups,
}: {
    studentId: number;
    groups: Props['groups'];
}) {
    const t = useT();

    return (
        <FormDialog
            title={t('Add to group')}
            form={enrollmentRoutes.store.form(studentId)}
            trigger={
                <Button>
                    <Plus />
                    {t('Add to group')}
                </Button>
            }
        >
            {(errors) => (
                <>
                    <Field label={t('Group')} error={errors.group_id}>
                        <NativeSelect name="group_id" required>
                            {groups.map((g) => (
                                <option key={g.id} value={g.id}>
                                    {g.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('Joined on')} error={errors.joined_at}>
                            <Input
                                name="joined_at"
                                type="date"
                                defaultValue={today()}
                                required
                            />
                        </Field>
                        <Field
                            label={t('Trial lessons')}
                            error={errors.trial_lessons}
                        >
                            <Input
                                name="trial_lessons"
                                type="number"
                                min={0}
                                max={30}
                                defaultValue={1}
                            />
                        </Field>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <Field
                            label={t('Discount type')}
                            error={errors.discount_type}
                        >
                            <NativeSelect name="discount_type" defaultValue="">
                                <option value="">{t('No discount')}</option>
                                <option value="percent">%</option>
                                <option value="amount">
                                    {t("so'm per month")}
                                </option>
                            </NativeSelect>
                        </Field>
                        <Field
                            label={t('Discount')}
                            error={errors.discount_value}
                        >
                            <MoneyInput
                                name="discount_value"
                                min={0}
                                defaultValue={0}
                            />
                        </Field>
                    </div>
                </>
            )}
        </FormDialog>
    );
}

function ReversePaymentDialog({ payment }: { payment: Payment }) {
    const t = useT();

    return (
        <FormDialog
            destructive
            title={t('Cancel payment')}
            description={t(
                'Payment :amount (№ :no) will be cancelled. Teacher shares paid from it are taken back.',
                { amount: formatMoney(payment.amount), no: payment.receipt_no },
            )}
            form={paymentRoutes.reverse.form(payment.id)}
            submitLabel={t('Cancel payment')}
            trigger={
                <Button size="sm" variant="ghost" className="text-danger">
                    {t('Cancel')}
                </Button>
            }
        >
            {(errors) => (
                <Field label={t('Reason')} error={errors.reason}>
                    <Input name="reason" required />
                </Field>
            )}
        </FormDialog>
    );
}

StudentShow.layout = {
    breadcrumbs: [{ title: 'Students', href: studentRoutes.index() }],
};
