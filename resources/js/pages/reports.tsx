import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import PeriodFilter from '@/components/period-filter';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatMoney } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { reports } from '@/routes';
import students from '@/routes/students';
import teachers from '@/routes/teachers';

type Props = {
    period: { from: string; to: string };
    totals: Record<string, { total: number; count: number }>;
    cashChange: number;
    expenses: { category: string; total: number }[];
    teachers: {
        teacher: string | null;
        teacher_id: number;
        opening: number;
        earned: number;
        paid: number;
        closing: number;
    }[];
    debtors: {
        student_id: number;
        student: string;
        phone: string | null;
        group: string;
        debt: number;
        status: string;
    }[];
    groups: {
        group: string;
        teacher: string | null;
        students: number;
        income: number;
        revenue: number;
        teacher_share: number;
        center_share: number;
    }[];
    leadSources: { source: string; total: number; converted: number }[];
};

export default function Reports({
    period,
    totals,
    cashChange,
    expenses,
    teachers: teacherRows,
    debtors,
    groups,
    leadSources,
}: Props) {
    const t = useT();
    const [tab, setTab] = useState<
        'finance' | 'teachers' | 'debtors' | 'groups' | 'leads'
    >('finance');
    const sum = (type: string) => totals[type]?.total ?? 0;
    const income = sum('student_payment');
    const maxExpense = Math.max(1, ...expenses.map((e) => e.total));

    return (
        <>
            <Head title={t('Reports')} />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={t('Reports')}
                    actions={<PeriodFilter from={period.from} to={period.to} />}
                />
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        tone="primary"
                        label={t('Payments received')}
                        value={formatMoney(income)}
                        hint={t(':n payments', {
                            n: totals.student_payment?.count ?? 0,
                        })}
                    />
                    <StatCard
                        label={t('Expenses')}
                        value={formatMoney(sum('expense'))}
                    />
                    <StatCard
                        label={t('Salaries paid')}
                        value={formatMoney(sum('teacher_payout'))}
                    />
                    <StatCard
                        tone="success"
                        label={t('Profit withdrawn')}
                        value={formatMoney(sum('owner_withdrawal'))}
                        hint={`${t('Change in cashboxes')}: ${formatMoney(cashChange)}`}
                    />
                </div>

                <div className="flex flex-wrap gap-1 border-b">
                    {(
                        [
                            'finance',
                            'teachers',
                            'debtors',
                            'groups',
                            'leads',
                        ] as const
                    ).map((key) => (
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
                                    finance: 'Expenses by category',
                                    teachers: 'Teachers',
                                    debtors: 'Debtors',
                                    groups: 'Groups',
                                    leads: 'Lead sources',
                                }[key],
                            )}
                        </button>
                    ))}
                </div>

                {tab === 'finance' && (
                    <section className="space-y-2 rounded-xl border bg-card p-4">
                        {expenses.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                {t('No expenses in this period.')}
                            </p>
                        )}
                        {expenses.map((e) => (
                            <div
                                key={e.category}
                                className="grid grid-cols-[10rem_1fr_7rem] items-center gap-3 text-sm"
                            >
                                <span className="truncate font-medium">
                                    {e.category}
                                </span>
                                <div className="h-3 rounded-full bg-muted">
                                    <div
                                        className="h-3 rounded-full bg-highlight"
                                        style={{
                                            width: `${(e.total / maxExpense) * 100}%`,
                                        }}
                                    />
                                </div>
                                <Money
                                    value={e.total}
                                    className="text-right font-semibold"
                                />
                            </div>
                        ))}
                    </section>
                )}

                {tab === 'teachers' && (
                    <div className="space-y-2">
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Start of period + earned − paid = end of period. Salaries paid in this period can include money earned earlier, so paid may be more than earned.',
                            )}
                        </p>
                        <SimpleTable
                            heads={[
                                t('Teacher'),
                                t('At period start'),
                                t('Earned'),
                                t('Paid out'),
                                t('At period end'),
                            ]}
                            rows={teacherRows.map((r) => [
                                <Link
                                    key="n"
                                    href={teachers.show(r.teacher_id)}
                                    className="font-semibold hover:text-primary"
                                >
                                    {r.teacher}
                                </Link>,
                                <Money
                                    key="o"
                                    value={r.opening}
                                    className="text-muted-foreground"
                                />,
                                <Money key="e" value={r.earned} />,
                                <Money key="p" value={r.paid} />,
                                <Money
                                    key="c"
                                    value={r.closing}
                                    className="font-bold"
                                />,
                            ])}
                        />
                    </div>
                )}

                {tab === 'debtors' && (
                    <div className="space-y-2">
                        <div className="flex justify-between text-sm">
                            <span>
                                {t('Total debt')}:{' '}
                                <Money
                                    value={debtors.reduce(
                                        (s, d) => s + d.debt,
                                        0,
                                    )}
                                    className="font-bold text-danger"
                                />
                            </span>
                            <Button asChild size="sm" variant="outline">
                                <a
                                    href={
                                        reports({
                                            query: {
                                                ...period,
                                                export: 'debtors',
                                            },
                                        }).url
                                    }
                                >
                                    <Download />
                                    Excel
                                </a>
                            </Button>
                        </div>
                        <SimpleTable
                            heads={[
                                t('Student'),
                                t('Phone'),
                                t('Group'),
                                t('Debt'),
                            ]}
                            rows={debtors.map((d) => [
                                <Link
                                    key="s"
                                    href={students.show(d.student_id)}
                                    className="font-semibold hover:text-primary"
                                >
                                    {d.student}
                                </Link>,
                                d.phone ?? '—',
                                `${d.group}${d.status !== 'active' ? ` (${t(`status.${d.status}`)})` : ''}`,
                                <Money
                                    key="d"
                                    value={d.debt}
                                    className="font-bold text-danger"
                                />,
                            ])}
                        />
                    </div>
                )}

                {tab === 'groups' && (
                    <div className="space-y-2">
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Shares are counted from lessons held and paid in this period, so money paid earlier for these lessons is included.',
                            )}
                        </p>
                        <SimpleTable
                            heads={[
                                t('Group'),
                                t('Teacher'),
                                t('Students'),
                                t('Payments received'),
                                t('Lesson revenue'),
                                t('Teacher share'),
                                t('Center share'),
                            ]}
                            rows={groups.map((g) => [
                                g.group,
                                g.teacher ?? '—',
                                g.students,
                                <Money key="i" value={g.income} />,
                                <Money key="r" value={g.revenue} />,
                                <Money key="t" value={g.teacher_share} />,
                                <Money
                                    key="c"
                                    value={g.center_share}
                                    className="font-bold"
                                />,
                            ])}
                        />
                    </div>
                )}

                {tab === 'leads' && (
                    <SimpleTable
                        heads={[
                            t('Source'),
                            t('Leads'),
                            t('Became students'),
                            t('Conversion'),
                        ]}
                        rows={leadSources.map((l) => [
                            t(`source.${l.source}`),
                            l.total,
                            l.converted,
                            `${l.total ? Math.round((l.converted / l.total) * 100) : 0}%`,
                        ])}
                    />
                )}
            </div>
        </>
    );
}

function SimpleTable({
    heads,
    rows,
}: {
    heads: string[];
    rows: React.ReactNode[][];
}) {
    const t = useT();

    return (
        <div className="overflow-hidden rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-12 text-center">№</TableHead>
                        {heads.map((h, i) => (
                            <TableHead
                                key={h}
                                className={
                                    i > 0 && i === heads.length - 1
                                        ? 'text-right'
                                        : ''
                                }
                            >
                                {h}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.length === 0 && (
                        <TableRow>
                            <TableCell
                                colSpan={heads.length + 1}
                                className="py-6 text-center text-muted-foreground"
                            >
                                {t('No data.')}
                            </TableCell>
                        </TableRow>
                    )}
                    {rows.map((row, i) => (
                        <TableRow key={i}>
                            <TableCell className="w-12 text-center text-muted-foreground tabular-nums">
                                {i + 1}
                            </TableCell>
                            {row.map((cell, j) => (
                                <TableCell
                                    key={j}
                                    className={
                                        j > 0 && j === row.length - 1
                                            ? 'text-right'
                                            : ''
                                    }
                                >
                                    {cell}
                                </TableCell>
                            ))}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

Reports.layout = {
    breadcrumbs: [{ title: 'Reports', href: reports() }],
};
