import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    GraduationCap,
    PiggyBank,
    TrendingUp,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-react';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { formatMoney, formatMonth } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { dashboard } from '@/routes';
import cashboxRoutes from '@/routes/cashboxes';

type Props = {
    stats: {
        todayIncome: number;
        monthIncome: number;
        cash: number;
        teacherLiability: number;
        prepaid: number;
        freeProfit: number;
        debtorsCount: number;
        debtTotal: number;
        newLeads: number;
        activeStudents: number;
    };
    cashboxes: {
        id: number;
        name: string;
        method: string;
        balance: number;
        branch: { name: string };
    }[];
    months: { month: string; income: number; expenses: number }[];
};

export default function Dashboard({ stats, cashboxes, months }: Props) {
    const t = useT();
    const locale = useLocale();
    const max = Math.max(1, ...months.flatMap((m) => [m.income, m.expenses]));

    return (
        <>
            <Head title={t('Dashboard')} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader title={t('Dashboard')} />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        tone="primary"
                        label={t('Today income')}
                        value={formatMoney(stats.todayIncome)}
                        hint={`${t('This month')}: ${formatMoney(stats.monthIncome)}`}
                        icon={Banknote}
                    />
                    <StatCard
                        label={t('Cash in all cashboxes')}
                        value={formatMoney(stats.cash)}
                        icon={Wallet}
                    />
                    <StatCard
                        tone="success"
                        label={t('Free profit')}
                        value={formatMoney(stats.freeProfit)}
                        hint={t('Cash − teacher money − prepaid')}
                        icon={PiggyBank}
                    />
                    <StatCard
                        tone="danger"
                        label={t('Debtors')}
                        value={stats.debtorsCount}
                        hint={`${t('Total debt')}: ${formatMoney(stats.debtTotal)}`}
                        icon={AlertTriangle}
                    />
                    <StatCard
                        label={t('Active students')}
                        value={stats.activeStudents}
                        icon={GraduationCap}
                    />
                    <StatCard
                        label={t('Owed to teachers')}
                        value={formatMoney(stats.teacherLiability)}
                        icon={Users}
                    />
                    <StatCard
                        label={t('Paid in advance')}
                        value={formatMoney(stats.prepaid)}
                        icon={TrendingUp}
                    />
                    <StatCard
                        tone="highlight"
                        label={t('New leads')}
                        value={stats.newLeads}
                        icon={UserPlus}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <section className="rounded-xl border bg-card p-4 lg:col-span-2">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="font-bold">
                                {t('Income and expenses')}
                            </h2>
                            <div className="flex gap-3 text-xs">
                                <span className="flex items-center gap-1">
                                    <span className="size-2.5 rounded-sm bg-primary" />
                                    {t('Income')}
                                </span>
                                <span className="flex items-center gap-1">
                                    <span className="size-2.5 rounded-sm bg-highlight" />
                                    {t('Expenses')}
                                </span>
                            </div>
                        </div>
                        <div className="flex h-56 items-end gap-3">
                            {months.map((m) => (
                                <div
                                    key={m.month}
                                    className="flex flex-1 flex-col items-center gap-2"
                                >
                                    <div className="flex h-44 w-full items-end justify-center gap-1">
                                        <div
                                            className="w-1/3 rounded-t bg-primary"
                                            style={{
                                                height: `${(m.income / max) * 100}%`,
                                            }}
                                            title={formatMoney(m.income)}
                                        />
                                        <div
                                            className="w-1/3 rounded-t bg-highlight"
                                            style={{
                                                height: `${(m.expenses / max) * 100}%`,
                                            }}
                                            title={formatMoney(m.expenses)}
                                        />
                                    </div>
                                    <span className="text-[11px] text-muted-foreground">
                                        {formatMonth(m.month, locale)
                                            .split(' ')[0]
                                            .slice(0, 3)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="rounded-xl border bg-card p-4">
                        <h2 className="mb-3 font-bold">{t('Cashboxes')}</h2>
                        <ul className="divide-y">
                            {cashboxes.map((c) => (
                                <li key={c.id}>
                                    <Link
                                        href={cashboxRoutes.show(c.id)}
                                        className="flex items-center justify-between py-2 text-sm hover:text-primary"
                                    >
                                        <span>
                                            {c.name}
                                            <span className="ml-1 text-xs text-muted-foreground">
                                                · {c.branch.name}
                                            </span>
                                        </span>
                                        <Money
                                            value={c.balance}
                                            className="font-semibold"
                                        />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
