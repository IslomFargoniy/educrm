import { Head } from '@inertiajs/react';
import HistoryTable from '@/components/history-table';
import type { History } from '@/components/history-table';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import PayoutDialog from '@/components/payout-dialog';
import PeriodFilter from '@/components/period-filter';
import StatCard from '@/components/stat-card';
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
    cashboxes: { id: number; name: string; balance: number }[];
};

export default function TeacherShow({
    teacher,
    history,
    pending,
    period,
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
                    <StatCard
                        label={t('Waiting for payment')}
                        value={formatMoney(pending.total)}
                        hint={t(
                            'Lessons taught, students have not paid yet. Not on the balance.',
                        )}
                    />
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-lg font-bold">
                        {t('Balance history')}
                    </h2>
                    <PeriodFilter from={period.from} to={period.to} />
                </div>
                <HistoryTable
                    history={history}
                    context="teacher"
                    labels={{
                        income: t('Total earned'),
                        expense: t('Total taken'),
                    }}
                />

                {pending.items.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="text-lg font-bold">
                            {t('Waiting for payment')}
                        </h2>
                        <div className="rounded-xl border bg-card">
                            <ul className="divide-y text-sm">
                                {pending.items.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex items-center justify-between px-3 py-2"
                                    >
                                        <span>
                                            <span className="font-semibold">
                                                {item.student}
                                            </span>{' '}
                                            — {item.group},{' '}
                                            {t('lesson :date', {
                                                date: formatDate(
                                                    item.lesson_date,
                                                ),
                                            })}
                                            {item.is_substitute && (
                                                <span className="ml-1 text-xs text-highlight">
                                                    ({t('substitute')})
                                                </span>
                                            )}
                                        </span>
                                        <Money
                                            value={item.amount}
                                            className="text-muted-foreground"
                                        />
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

TeacherShow.layout = {
    breadcrumbs: [{ title: 'Teachers', href: teachers.index() }],
};
