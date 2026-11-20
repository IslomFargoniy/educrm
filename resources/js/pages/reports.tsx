import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import RowNumber from '@/components/row-number';
import Money from '@/components/money';
import { BarChart, PieChart } from '@/components/report-chart';
import { useShowMore } from '@/components/show-more';
import type { ChartItem } from '@/components/report-chart';
import PageHeader from '@/components/page-header';
import PeriodFilter from '@/components/period-filter';
import ReportFilters from '@/components/report-filters';
import type {
    ReportFilterOptions,
    ReportFilterValues,
} from '@/components/report-filters';
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
import { formatDate, formatMoney } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { reports } from '@/routes';
import students from '@/routes/students';
import teachers from '@/routes/teachers';

type Props = {
    period: { from: string; to: string };
    totals: Record<string, { total: number; count: number }>;
    cashChange: number | null;
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
        teacher_percent: number;
        percent_range: [number, number] | null;
        monthly_plan: number;
        charged: number;
        income: number;
        revenue: number;
        teacher_share: number;
        center_share: number;
        debt: number;
    }[];
    leadSources: (Funnel & { source: string })[];
    leadCourses: (Funnel & { course: string | null })[];
    courses: {
        course: string | null;
        groups: number;
        students: number;
        monthly_plan: number;
        charged: number;
        income: number;
        revenue: number;
        teacher_share: number;
        center_share: number;
        debt: number;
        leads: number;
        leads_joined: number;
    }[];
    studentFlow: {
        leads: Funnel;
        joined: number;
        transferred: number;
        left: number;
    };
    filters: ReportFilterValues;
    filterOptions: ReportFilterOptions;
    visits: {
        date: string;
        student_id: number;
        student: string | null;
        first_in: string | null;
        last_out: string | null;
        passes: number;
    }[];
};

type Funnel = {
    total: number;
    contacted: number;
    trial: number;
    joined: number;
    studying: number;
    paid: number;
    rejected: number;
};

const metricLabels = {
    income: 'Payments received',
    revenue: 'Lesson revenue',
    charged: 'To be received',
    debt: 'Debt',
} as const;

export default function Reports({
    period,
    totals,
    cashChange,
    expenses,
    teachers: teacherRows,
    debtors,
    groups,
    leadSources,
    leadCourses,
    studentFlow,
    courses,
    visits,
    filters,
    filterOptions,
}: Props) {
    const t = useT();
    const funnelHeads = (first: string) => [
        first,
        t('Leads'),
        t('Contacted'),
        t('Trial lesson'),
        t('Joined a group'),
        t('Studying now'),
        t('Paid'),
        t('Rejected'),
        t('Conversion'),
    ];
    const [tab, setTab] = useState<
        | 'finance'
        | 'teachers'
        | 'debtors'
        | 'groups'
        | 'courses'
        | 'leads'
        | 'visits'
    >('finance');
    const [view, setView] = useState<'table' | 'bar' | 'pie'>('table');
    const [metric, setMetric] = useState<
        'income' | 'revenue' | 'debt' | 'charged'
    >('income');
    const chartable = tab !== 'visits';
    const metricOf = (row: {
        income: number;
        revenue: number;
        debt: number;
        charged: number;
    }) => row[metric];
    const chart: { items: ChartItem[]; money: boolean; title: string } =
        (() => {
            switch (tab) {
                case 'finance':
                    return {
                        items: expenses.map((e) => ({
                            label: e.category,
                            value: e.total,
                        })),
                        money: true,
                        title: t('Expenses by category'),
                    };
                case 'teachers':
                    return {
                        items: teacherRows.map((r) => ({
                            label: r.teacher ?? '—',
                            value: r.earned,
                        })),
                        money: true,
                        title: t('Earned in the period'),
                    };
                case 'debtors':
                    return {
                        items: debtors.map((d) => ({
                            label: `${d.student} · ${d.group}`,
                            value: d.debt,
                        })),
                        money: true,
                        title: t('Debt'),
                    };
                case 'groups':
                    return {
                        items: groups.map((g) => ({
                            label: g.group,
                            value: metricOf(g),
                        })),
                        money: true,
                        title: t(metricLabels[metric]),
                    };
                case 'courses':
                    return {
                        items: courses.map((c) => ({
                            label: c.course ?? t('No course'),
                            value: metricOf(c),
                        })),
                        money: true,
                        title: t(metricLabels[metric]),
                    };
                case 'leads':
                    return {
                        items: leadSources.map((l) => ({
                            label: t(`source.${l.source}`),
                            value: l.total,
                        })),
                        money: false,
                        title: t('Leads by source'),
                    };
                default:
                    return { items: [], money: true, title: '' };
            }
        })();
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
                <ReportFilters filters={filters} options={filterOptions} />
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
                        hint={
                            cashChange === null
                                ? undefined
                                : `${t('Change in cashboxes')}: ${formatMoney(cashChange)}`
                        }
                    />
                </div>

                <div className="flex flex-wrap gap-1 border-b">
                    {(
                        [
                            'finance',
                            'teachers',
                            'debtors',
                            'groups',
                            'courses',
                            'leads',
                            ...(visits.length > 0 ? (['visits'] as const) : []),
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
                                    courses: 'Courses',
                                    leads: 'Leads and students',
                                    visits: 'Arrivals and departures',
                                }[key],
                            )}
                        </button>
                    ))}
                </div>

                {chartable && (
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="inline-flex rounded-lg border bg-card p-0.5">
                            {(
                                [
                                    ['table', 'Table'],
                                    ['bar', 'Bar chart'],
                                    ['pie', 'Pie chart'],
                                ] as const
                            ).map(([value, label]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => setView(value)}
                                    className={cn(
                                        'rounded-md px-3 py-1 text-xs font-semibold',
                                        view === value
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {t(label)}
                                </button>
                            ))}
                        </div>
                        {view !== 'table' &&
                            (tab === 'groups' || tab === 'courses') && (
                                <select
                                    value={metric}
                                    onChange={(e) =>
                                        setMetric(
                                            e.target.value as typeof metric,
                                        )
                                    }
                                    className="h-8 rounded-md border bg-card px-2 text-xs font-semibold"
                                    aria-label={t('Measure')}
                                >
                                    {(
                                        Object.keys(
                                            metricLabels,
                                        ) as (keyof typeof metricLabels)[]
                                    ).map((key) => (
                                        <option key={key} value={key}>
                                            {t(metricLabels[key])}
                                        </option>
                                    ))}
                                </select>
                            )}
                    </div>
                )}

                {chartable && view !== 'table' && (
                    <section className="space-y-3 rounded-xl border bg-card p-4">
                        <h3 className="text-sm font-bold">{chart.title}</h3>
                        {view === 'bar' ? (
                            <BarChart items={chart.items} money={chart.money} />
                        ) : (
                            <PieChart items={chart.items} money={chart.money} />
                        )}
                    </section>
                )}

                {view === 'table' && tab === 'finance' && (
                    <section className="space-y-2 rounded-xl border bg-card p-4">
                        {expenses.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                {t('No expenses in this period.')}
                            </p>
                        )}
                        {expenses.map((e, index) => (
                            <div
                                key={e.category}
                                className="grid grid-cols-[10rem_1fr_7rem] items-center gap-3 text-sm"
                            >
                                <span className="truncate font-medium">
                                    <RowNumber
                                        value={index + 1}
                                        className="mr-2"
                                    />
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

                {view === 'table' && tab === 'teachers' && (
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

                {view === 'table' && tab === 'debtors' && (
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

                {view === 'table' && tab === 'groups' && (
                    <div className="space-y-2">
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Monthly plan: monthly price of active students after discounts. To be received: price of the lessons held in the period. Shares are counted from lessons held and paid in this period, so money paid earlier for these lessons is included.',
                            )}
                        </p>
                        <SimpleTable
                            heads={[
                                t('Group'),
                                t('Teacher'),
                                t('Students'),
                                t('Teacher percent'),
                                t('Monthly plan'),
                                t('To be received'),
                                t('Payments received'),
                                t('Lesson revenue'),
                                t('Teacher share'),
                                t('Center share'),
                            ]}
                            rows={groups.map((g) => [
                                g.group,
                                g.teacher ?? '—',
                                g.students,
                                <span key="pct" className="tabular-nums">
                                    {g.percent_range
                                        ? `${g.percent_range[0]}–${g.percent_range[1]}%`
                                        : `${g.teacher_percent}%`}
                                </span>,
                                <Money key="m" value={g.monthly_plan} />,
                                <Money key="ch" value={g.charged} />,
                                <Money key="i" value={g.income} />,
                                <Money key="r" value={g.revenue} />,
                                <Money key="t" value={g.teacher_share} />,
                                <Money
                                    key="c"
                                    value={g.center_share}
                                    className="font-bold"
                                />,
                            ])}
                            footer={
                                groups.length > 1 && [
                                    t('Total'),
                                    '',
                                    groups.reduce(
                                        (sum, g) => sum + g.students,
                                        0,
                                    ),
                                    '',
                                    ...(
                                        [
                                            'monthly_plan',
                                            'charged',
                                            'income',
                                            'revenue',
                                            'teacher_share',
                                            'center_share',
                                        ] as const
                                    ).map((key) => (
                                        <Money
                                            key={key}
                                            value={groups.reduce(
                                                (sum, g) => sum + g[key],
                                                0,
                                            )}
                                        />
                                    )),
                                ]
                            }
                        />
                    </div>
                )}

                {view === 'table' && tab === 'courses' && (
                    <div className="space-y-2">
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Groups of each course added up for the period. Leads: leads of the period who asked for this course and how many of them joined a group.',
                            )}
                        </p>
                        <SimpleTable
                            heads={[
                                t('Course'),
                                t('Groups'),
                                t('Students'),
                                t('Monthly plan'),
                                t('To be received'),
                                t('Payments received'),
                                t('Lesson revenue'),
                                t('Teacher share'),
                                t('Center share'),
                                t('Debt'),
                                t('Leads: joined / total'),
                            ]}
                            rows={courses.map((c) => [
                                <span key="n" className="font-semibold">
                                    {c.course ?? t('No course')}
                                </span>,
                                c.groups,
                                c.students,
                                <Money key="m" value={c.monthly_plan} />,
                                <Money key="ch" value={c.charged} />,
                                <Money key="i" value={c.income} />,
                                <Money key="r" value={c.revenue} />,
                                <Money key="t" value={c.teacher_share} />,
                                <Money
                                    key="c"
                                    value={c.center_share}
                                    className="font-bold"
                                />,
                                <Money
                                    key="d"
                                    value={c.debt}
                                    className={c.debt > 0 ? 'text-danger' : ''}
                                />,
                                <span key="l" className="tabular-nums">
                                    {c.leads_joined}/{c.leads}
                                </span>,
                            ])}
                            footer={
                                courses.length > 1 && [
                                    t('Total'),
                                    courses.reduce((s, c) => s + c.groups, 0),
                                    courses.reduce((s, c) => s + c.students, 0),
                                    ...(
                                        [
                                            'monthly_plan',
                                            'charged',
                                            'income',
                                            'revenue',
                                            'teacher_share',
                                            'center_share',
                                            'debt',
                                        ] as const
                                    ).map((key) => (
                                        <Money
                                            key={key}
                                            value={courses.reduce(
                                                (s, c) => s + c[key],
                                                0,
                                            )}
                                        />
                                    )),
                                    `${courses.reduce((s, c) => s + c.leads_joined, 0)}/${courses.reduce((s, c) => s + c.leads, 0)}`,
                                ]
                            }
                        />
                    </div>
                )}

                {tab === 'visits' && (
                    <div className="space-y-2">
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'From the face terminals: for each day, when the student first came in and last went out.',
                            )}
                        </p>
                        <SimpleTable
                            heads={[
                                t('Date'),
                                t('Student'),
                                t('Came in'),
                                t('Went out'),
                                t('Passes'),
                            ]}
                            rows={visits.map((v) => [
                                formatDate(v.date),
                                <Link
                                    key="s"
                                    href={students.show(v.student_id)}
                                    className="font-semibold hover:underline"
                                >
                                    {v.student}
                                </Link>,
                                v.first_in ?? '—',
                                v.last_out ?? '—',
                                v.passes,
                            ])}
                        />
                    </div>
                )}

                {view === 'table' && tab === 'leads' && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-2 md:grid-cols-4">
                            {[
                                {
                                    label: t('New leads'),
                                    value: studentFlow.leads.total,
                                },
                                {
                                    label: t('Leads who joined a group'),
                                    value: studentFlow.leads.joined,
                                    hint: `${percentOf(studentFlow.leads.joined, studentFlow.leads.total)}%`,
                                },
                                {
                                    label: t('Students added to groups'),
                                    value: studentFlow.joined,
                                    hint: studentFlow.transferred
                                        ? t('+:count moved from other groups', {
                                              count: studentFlow.transferred,
                                          })
                                        : undefined,
                                },
                                {
                                    label: t('Left groups'),
                                    value: studentFlow.left,
                                },
                            ].map((card) => (
                                <div
                                    key={card.label}
                                    className="rounded-xl border bg-card p-3"
                                >
                                    <div className="text-xs text-muted-foreground">
                                        {card.label}
                                    </div>
                                    <div className="text-2xl font-bold tabular-nums">
                                        {card.value}
                                    </div>
                                    {card.hint && (
                                        <div className="text-xs text-muted-foreground">
                                            {card.hint}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Leads created in the period and how far they got: contacted, came to a trial lesson, joined a group, study now, paid. Conversion = joined a group ÷ leads.',
                            )}
                        </p>
                        <h3 className="font-bold">{t('By source')}</h3>
                        <SimpleTable
                            heads={funnelHeads(t('Source'))}
                            rows={leadSources.map((l) =>
                                funnelCells(t(`source.${l.source}`), l),
                            )}
                            footer={
                                leadSources.length > 1 &&
                                funnelCells(t('Total'), studentFlow.leads)
                            }
                        />
                        <h3 className="font-bold">{t('By course')}</h3>
                        <SimpleTable
                            heads={funnelHeads(t('Course'))}
                            rows={leadCourses.map((l) =>
                                funnelCells(l.course ?? '—', l),
                            )}
                        />
                    </div>
                )}
            </div>
        </>
    );
}

function percentOf(part: number, total: number): number {
    return total ? Math.round((part / total) * 100) : 0;
}

function funnelCells(label: string, f: Funnel): React.ReactNode[] {
    return [
        label,
        f.total,
        f.contacted,
        f.trial,
        f.joined,
        f.studying,
        f.paid,
        f.rejected,
        `${percentOf(f.joined, f.total)}%`,
    ];
}

function SimpleTable({
    heads,
    rows,
    footer,
}: {
    heads: string[];
    rows: React.ReactNode[][];
    footer?: React.ReactNode[] | false;
}) {
    const t = useT();

    const { shown, button: moreButton } = useShowMore(rows);

    return (
        <div className="overflow-x-auto rounded-xl border bg-card">
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
                    {shown.map((row, i) => (
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
                    {footer && (
                        <TableRow className="bg-muted/50 font-bold hover:bg-muted/50">
                            <TableCell />
                            {footer.map((cell, j) => (
                                <TableCell
                                    key={j}
                                    className={
                                        j > 0 && j === footer.length - 1
                                            ? 'text-right'
                                            : ''
                                    }
                                >
                                    {cell}
                                </TableCell>
                            ))}
                        </TableRow>
                    )}
                </TableBody>
            </Table>
            {moreButton}
        </div>
    );
}

Reports.layout = {
    breadcrumbs: [{ title: 'Reports', href: reports() }],
};
