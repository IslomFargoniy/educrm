import { Head } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import RowNumber from '@/components/row-number';
import HistoryTable from '@/components/history-table';
import type { History } from '@/components/history-table';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import PayoutDialog from '@/components/payout-dialog';
import PeriodFilter from '@/components/period-filter';
import StatCard from '@/components/stat-card';
import TeacherMonthReport from '@/components/teacher-month-report';
import type { ReportGroup } from '@/components/teacher-month-report';
import { useCan } from '@/hooks/use-can';
import { formatDate, formatMoney, formatPhone } from '@/lib/format';
import { useT } from '@/lib/i18n';
import teachers from '@/routes/teachers';

type Props = {
    teacher: {
        id: number;
        name: string;
        phone: string | null;
        balance: number;
        groups: { id: number; name: string; teacher_percent: number }[];
    };
    history: History;
    pending: {
        total: number;
        items: {
            id: number;
            student: string;
            group: string;
            lesson_date: string;
            amount: number;
            is_substitute: boolean;
        }[];
    };
    period: { from: string; to: string };
    month: string;
    report: ReportGroup[];
    cashboxes: { id: number; name: string; balance: number }[];
};

type PendingItem = Props['pending']['items'][number];

/**
 * Same student in the same group → one row with the lessons summed.
 */
function groupPending(items: PendingItem[]) {
    const rows = new Map<
        string,
        {
            key: string;
            student: string;
            group: string;
            total: number;
            items: PendingItem[];
        }
    >();

    for (const item of items) {
        const key = `${item.student}|${item.group}`;
        const row = rows.get(key) ?? {
            key,
            student: item.student,
            group: item.group,
            total: 0,
            items: [],
        };
        row.total += item.amount;
        row.items.push(item);
        rows.set(key, row);
    }

    return [...rows.values()];
}

export default function TeacherShow({
    teacher,
    history,
    pending,
    period,
    month,
    report,
    cashboxes,
}: Props) {
    const t = useT();
    const { can } = useCan();

    return (
        <>
            <Head title={teacher.name} />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={teacher.name}
                    description={[
                        formatPhone(teacher.phone),
                        teacher.groups
                            .map((g) => `${g.name} (${g.teacher_percent}%)`)
                            .join(', '),
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                    actions={
                        can('payouts.create') &&
                        cashboxes.length > 0 && (
                            <PayoutDialog
                                teacher={teacher}
                                cashboxes={cashboxes}
                            />
                        )
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2">
                    <StatCard
                        tone="primary"
                        label={t('Balance (can be taken)')}
                        value={formatMoney(teacher.balance)}
                    />
                    <a href="#pending" className="block rounded-xl">
                        <StatCard
                            tone={pending.total > 0 ? 'danger' : 'default'}
                            label={t('Waiting for payment')}
                            value={formatMoney(-pending.total)}
                            hint={t(
                                'Lessons taught, students have not paid yet. Not on the balance.',
                            )}
                        />
                    </a>
                </div>

                {pending.items.length > 0 && (
                    <section id="pending" className="scroll-mt-20 space-y-2">
                        <h2 className="text-lg font-bold">
                            {t('Waiting for payment')}
                        </h2>
                        <div className="rounded-xl border bg-card">
                            <ul className="divide-y text-sm">
                                {groupPending(pending.items).map(
                                    (row, index) => (
                                        <li key={row.key}>
                                            <details className="group">
                                                <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2 hover:bg-muted/50">
                                                    <span>
                                                        <RowNumber
                                                            value={index + 1}
                                                            className="mr-2"
                                                        />
                                                        <span className="font-semibold">
                                                            {row.student}
                                                        </span>{' '}
                                                        — {row.group},{' '}
                                                        {t(':count lessons', {
                                                            count: String(
                                                                row.items
                                                                    .length,
                                                            ),
                                                        })}
                                                    </span>
                                                    <span className="flex items-center gap-2">
                                                        <Money
                                                            value={-row.total}
                                                            signed
                                                        />
                                                        <ChevronDown className="size-4 text-muted-foreground transition-transform group-open:rotate-180" />
                                                    </span>
                                                </summary>
                                                <ul className="pr-9 pb-2 pl-10">
                                                    {row.items.map((item) => (
                                                        <li
                                                            key={item.id}
                                                            className="flex justify-between py-1 text-muted-foreground"
                                                        >
                                                            <span>
                                                                {t(
                                                                    'lesson :date',
                                                                    {
                                                                        date: formatDate(
                                                                            item.lesson_date,
                                                                        ),
                                                                    },
                                                                )}
                                                                {item.is_substitute && (
                                                                    <span className="ml-1 text-xs text-highlight">
                                                                        (
                                                                        {t(
                                                                            'substitute',
                                                                        )}
                                                                        )
                                                                    </span>
                                                                )}
                                                            </span>
                                                            <Money
                                                                value={
                                                                    -item.amount
                                                                }
                                                                signed
                                                            />
                                                        </li>
                                                    ))}
                                                </ul>
                                            </details>
                                        </li>
                                    ),
                                )}
                            </ul>
                        </div>
                    </section>
                )}

                <TeacherMonthReport
                    teacherName={teacher.name}
                    month={month}
                    groups={report}
                />

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-lg font-bold">
                        {t('Balance history')}
                    </h2>
                    <PeriodFilter from={period.from} to={period.to} />
                </div>
                <HistoryTable
                    history={history}
                    context="teacher"
                    exportName={`${teacher.name} ${period.from} ${period.to}`}
                    labels={{
                        income: t('Total earned'),
                        expense: t('Total taken'),
                    }}
                />
            </div>
        </>
    );
}

TeacherShow.layout = {
    breadcrumbs: [{ title: 'Teachers', href: teachers.index() }],
};
