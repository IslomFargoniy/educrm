import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Download,
    HandCoins,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { toast } from 'sonner';
import Field from '@/components/field';
import FormDialog from '@/components/form-dialog';
import Money from '@/components/money';
import MoneyInput from '@/components/money-input';
import PageHeader from '@/components/page-header';
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
import { exportXlsx } from '@/lib/export-xlsx';
import { formatDate, formatMoney, formatMonth, shiftMonth } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import staffSalaries from '@/routes/staff-salaries';
import students from '@/routes/students';

type Staff = {
    id: number;
    name: string;
    role: string | null;
    fixed: number;
    kpi_rate: number;
    registered: number;
    first_payments: number;
    kpi: number;
    total: number;
    paid: number;
    left: number;
    students: { id: number; full_name: string; first_paid_at: string }[];
};

type Props = {
    month: string;
    staff: Staff[];
    cashboxes: { id: number; name: string; balance: number }[];
};

export default function StaffSalaries({ month, staff, cashboxes }: Props) {
    const t = useT();
    const locale = useLocale();
    const { can } = useCan();
    const [open, setOpen] = useState<number | null>(null);
    const go = (value: string) =>
        router.get(
            staffSalaries.index().url,
            { month: value },
            { preserveScroll: true },
        );
    const sum = (
        key:
            | 'fixed'
            | 'kpi'
            | 'total'
            | 'paid'
            | 'left'
            | 'first_payments'
            | 'registered',
    ) => staff.reduce((s, r) => s + r[key], 0);

    const download = () =>
        exportXlsx(
            `xodimlar-oyligi-${month}`,
            [
                t('Staff member'),
                t('Role'),
                t('Fixed salary'),
                t('Students taken in'),
                t('First payments'),
                t('KPI per student'),
                'KPI',
                t('Total salary'),
                t('Paid'),
                t('Left to pay'),
            ],
            staff.map((s) => [
                s.name,
                s.role ? t(`role.${s.role}`) : '',
                s.fixed,
                s.registered,
                s.first_payments,
                s.kpi_rate,
                s.kpi,
                s.total,
                s.paid,
                s.left,
            ]),
        ).catch(() => toast.error(t('Could not download the file.')));

    return (
        <>
            <Head title={t('Staff salaries')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('Staff salaries')}
                    description={t(
                        'Fixed salary + KPI for every new student the staff member took in who made the first payment this month. The KPI comes out of the center’s share.',
                    )}
                    actions={
                        <div className="flex items-center gap-2">
                            <div className="flex items-center gap-1 rounded-lg border bg-card p-1">
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    className="size-7"
                                    aria-label={t('Previous month')}
                                    onClick={() => go(shiftMonth(month, -1))}
                                >
                                    <ChevronLeft />
                                </Button>
                                <span className="px-2 text-sm font-semibold capitalize">
                                    {formatMonth(month, locale)}
                                </span>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    className="size-7"
                                    aria-label={t('Next month')}
                                    onClick={() => go(shiftMonth(month, 1))}
                                >
                                    <ChevronRight />
                                </Button>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={download}
                                disabled={staff.length === 0}
                            >
                                <Download />
                                Excel
                            </Button>
                        </div>
                    }
                />

                {staff.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        {t(
                            'No salary is set for anyone yet. Set the fixed salary and the KPI in Settings → Users.',
                        )}
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-xl border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10 text-center">
                                        №
                                    </TableHead>
                                    <TableHead>{t('Staff member')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Fixed salary')}
                                    </TableHead>
                                    <TableHead className="text-center">
                                        {t('Students taken in')}
                                    </TableHead>
                                    <TableHead className="text-center">
                                        {t('First payments')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        KPI
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Total salary')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Paid')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Left to pay')}
                                    </TableHead>
                                    {can('payouts.create') && (
                                        <TableHead className="w-10" />
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {staff.map((s, index) => (
                                    <Fragment key={s.id}>
                                        <TableRow
                                            className="cursor-pointer"
                                            onClick={() =>
                                                setOpen(
                                                    open === s.id ? null : s.id,
                                                )
                                            }
                                        >
                                            <TableCell className="text-center text-muted-foreground tabular-nums">
                                                {index + 1}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1 font-semibold">
                                                    <ChevronDown
                                                        className={cn(
                                                            'size-4 text-muted-foreground transition-transform',
                                                            open === s.id &&
                                                                'rotate-180',
                                                        )}
                                                    />
                                                    {s.name}
                                                </div>
                                                {s.role && (
                                                    <div className="pl-5 text-xs text-muted-foreground">
                                                        {t(`role.${s.role}`)}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Money value={s.fixed} />
                                            </TableCell>
                                            <TableCell className="text-center tabular-nums">
                                                {s.registered}
                                            </TableCell>
                                            <TableCell className="text-center font-semibold tabular-nums">
                                                {s.first_payments}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Money value={s.kpi} />
                                                <div className="text-xs text-muted-foreground tabular-nums">
                                                    {s.first_payments} ×{' '}
                                                    {formatMoney(s.kpi_rate)}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Money
                                                    value={s.total}
                                                    className="font-bold"
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Money value={s.paid} />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Money
                                                    value={s.left}
                                                    className={cn(
                                                        'font-semibold',
                                                        s.left > 0 &&
                                                            'text-danger',
                                                    )}
                                                />
                                            </TableCell>
                                            {can('payouts.create') && (
                                                <TableCell
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    <PayStaffDialog
                                                        staff={s}
                                                        month={month}
                                                        cashboxes={cashboxes}
                                                    />
                                                </TableCell>
                                            )}
                                        </TableRow>
                                        {open === s.id && (
                                            <TableRow className="bg-muted/30 hover:bg-muted/30">
                                                <TableCell />
                                                <TableCell
                                                    colSpan={
                                                        can('payouts.create')
                                                            ? 9
                                                            : 8
                                                    }
                                                    className="py-3"
                                                >
                                                    {s.students.length === 0 ? (
                                                        <span className="text-sm text-muted-foreground">
                                                            {t(
                                                                'No first payments this month.',
                                                            )}
                                                        </span>
                                                    ) : (
                                                        <ol className="grid gap-1 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                                            {s.students.map(
                                                                (st, i) => (
                                                                    <li
                                                                        key={
                                                                            st.id
                                                                        }
                                                                        className="flex justify-between gap-2"
                                                                    >
                                                                        <span>
                                                                            {i +
                                                                                1}
                                                                            .{' '}
                                                                            <Link
                                                                                href={students.show(
                                                                                    st.id,
                                                                                )}
                                                                                className="font-medium hover:text-primary"
                                                                            >
                                                                                {
                                                                                    st.full_name
                                                                                }
                                                                            </Link>
                                                                        </span>
                                                                        <span className="text-xs text-muted-foreground tabular-nums">
                                                                            {formatDate(
                                                                                st.first_paid_at,
                                                                            )}
                                                                        </span>
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ol>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </Fragment>
                                ))}
                            </TableBody>
                            {staff.length > 1 && (
                                <tfoot className="border-t bg-muted/40 font-bold">
                                    <tr>
                                        <td />
                                        <td className="p-2">{t('Total')}</td>
                                        <td className="p-2 text-right">
                                            <Money value={sum('fixed')} />
                                        </td>
                                        <td className="p-2 text-center tabular-nums">
                                            {sum('registered')}
                                        </td>
                                        <td className="p-2 text-center tabular-nums">
                                            {sum('first_payments')}
                                        </td>
                                        <td className="p-2 text-right">
                                            <Money value={sum('kpi')} />
                                        </td>
                                        <td className="p-2 text-right">
                                            <Money value={sum('total')} />
                                        </td>
                                        <td className="p-2 text-right">
                                            <Money value={sum('paid')} />
                                        </td>
                                        <td className="p-2 text-right">
                                            <Money value={sum('left')} />
                                        </td>
                                        {can('payouts.create') && <td />}
                                    </tr>
                                </tfoot>
                            )}
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

function PayStaffDialog({
    staff,
    month,
    cashboxes,
}: {
    staff: Staff;
    month: string;
    cashboxes: Props['cashboxes'];
}) {
    const t = useT();
    const [cashboxId, setCashboxId] = useState(cashboxes[0]?.id);

    return (
        <FormDialog
            title={`${t('Pay salary')}: ${staff.name}`}
            description={t('Total :total, paid :paid, left :left', {
                total: formatMoney(staff.total),
                paid: formatMoney(staff.paid),
                left: formatMoney(staff.left),
            })}
            form={staffSalaries.pay.form(staff.id)}
            submitLabel={t('Pay')}
            trigger={
                <Button
                    size="icon"
                    variant="ghost"
                    className="size-8"
                    aria-label={t('Pay salary')}
                    title={t('Pay salary')}
                >
                    <HandCoins />
                </Button>
            }
        >
            {(errors) => (
                <>
                    <input type="hidden" name="month" value={month} />
                    <Field label={t('Cashbox')} error={errors.cashbox_id}>
                        <NativeSelect
                            name="cashbox_id"
                            value={cashboxId}
                            onChange={(e) =>
                                setCashboxId(Number(e.target.value))
                            }
                        >
                            {cashboxes.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name} · {formatMoney(c.balance)}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <Field label={t('Amount')} error={errors.amount}>
                        <MoneyInput
                            name="amount"
                            defaultValue={Math.max(0, staff.left)}
                            min={1}
                            required
                        />
                    </Field>
                    <Field label={t('Note')} error={errors.note}>
                        <Input name="note" maxLength={255} />
                    </Field>
                </>
            )}
        </FormDialog>
    );
}

StaffSalaries.layout = {
    breadcrumbs: [{ title: 'Staff salaries', href: staffSalaries.index() }],
};
