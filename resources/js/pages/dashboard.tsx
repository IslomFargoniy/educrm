import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    ClipboardCheck,
    GraduationCap,
    PiggyBank,
    Plus,
    TrendingUp,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-react';
import RowNumber from '@/components/row-number';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { formatMoney, formatMonth } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { dashboard, schedule } from '@/routes';
import attendance from '@/routes/attendance';
import cashboxRoutes from '@/routes/cashboxes';
import lessonRoutes from '@/routes/lessons';
import studentRoutes from '@/routes/students';

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
    todayLessons: {
        id: number;
        time: string;
        group: string;
        teacher: string | null;
        room: string | null;
        status: string;
    }[];
};

const cashboxInitials: Record<string, string> = {
    cash: 'NQ',
    card: 'KR',
    click: 'CL',
    payme: 'PM',
};

export default function Dashboard({
    stats,
    cashboxes,
    months,
    todayLessons,
}: Props) {
    const t = useT();
    const locale = useLocale();
    const { can } = useCan();
    const { auth } = usePage().props;
    const cashTotal = cashboxes.reduce((sum, c) => sum + c.balance, 0);
    const today = new Intl.DateTimeFormat(
        locale === 'uz' ? 'uz-Latn' : locale,
        {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
        },
    ).format(new Date());
    const max = Math.max(1, ...months.flatMap((m) => [m.income, m.expenses]));

    return (
        <>
            <Head title={t('Dashboard')} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Hello, :name', { name: auth.user.name })}
                    description={<span className="capitalize">{today}</span>}
                    actions={
                        <>
                            {(can('attendance.mark') ||
                                can('lessons.manage')) && (
                                <Button variant="outline" asChild>
                                    <Link href={attendance.index()}>
                                        <ClipboardCheck />
                                        {t('Mark attendance')}
                                    </Link>
                                </Button>
                            )}
                            {can('payments.create') && (
                                <Button
                                    asChild
                                    className="bg-highlight font-bold text-highlight-foreground hover:bg-highlight/90"
                                >
                                    <Link href={studentRoutes.index()}>
                                        <Plus />
                                        {t('Receive payment')}
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

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
                    <section className="rounded-2xl border bg-card p-5 lg:col-span-2">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="font-extrabold">
                                {t('Income and expenses')}
                            </h2>
                            <div className="flex gap-3 text-xs">
                                <span className="flex items-center gap-1">
                                    <span className="size-2.5 rounded-sm bg-chart-1" />
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
                                            className="w-1/3 max-w-7 rounded-t-md bg-chart-1"
                                            style={{
                                                height: `${(m.income / max) * 100}%`,
                                            }}
                                            title={formatMoney(m.income)}
                                        />
                                        <div
                                            className="w-1/3 max-w-7 rounded-t-md bg-highlight"
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

                    <section className="flex flex-col rounded-2xl border bg-card p-5">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="font-extrabold">{t('Cashboxes')}</h2>
                            <Link
                                href={cashboxRoutes.index()}
                                className="text-sm font-bold text-secondary-foreground hover:underline"
                            >
                                {t('All')}
                            </Link>
                        </div>
                        <ul className="flex flex-col gap-1">
                            {cashboxes.map((c, index) => (
                                <li key={c.id}>
                                    <Link
                                        href={cashboxRoutes.show(c.id)}
                                        className="flex items-center gap-3 rounded-xl px-2 py-2 text-sm transition-colors hover:bg-muted"
                                    >
                                        <RowNumber value={index + 1} />
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-secondary text-xs font-extrabold text-secondary-foreground">
                                            {cashboxInitials[c.method] ??
                                                c.name
                                                    .slice(0, 2)
                                                    .toUpperCase()}
                                        </span>
                                        <span className="flex min-w-0 flex-1 flex-col">
                                            <span className="truncate font-bold">
                                                {c.name}
                                            </span>
                                            <span className="truncate text-xs text-muted-foreground">
                                                {c.branch.name}
                                            </span>
                                        </span>
                                        <Money
                                            value={c.balance}
                                            className="font-extrabold tabular-nums"
                                        />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                        <div className="mt-auto flex justify-between border-t px-2 pt-3 text-sm">
                            <span className="font-semibold text-muted-foreground">
                                {t('Total')}
                            </span>
                            <Money
                                value={cashTotal}
                                className="font-extrabold tabular-nums"
                            />
                        </div>
                    </section>
                </div>

                <section className="rounded-2xl border bg-card p-5">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="font-extrabold">{t('Today lessons')}</h2>
                        {can('groups.view') && (
                            <Link
                                href={schedule()}
                                className="text-sm font-bold text-secondary-foreground hover:underline"
                            >
                                {t('Schedule')}
                            </Link>
                        )}
                    </div>
                    {todayLessons.length === 0 ? (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            {t('No lessons today.')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <ul className="flex min-w-[560px] flex-col">
                                {todayLessons.map((lesson, index) => (
                                    <li key={lesson.id}>
                                        <Link
                                            href={lessonRoutes.show(lesson.id)}
                                            className="grid grid-cols-[24px_72px_minmax(0,1.4fr)_minmax(0,1fr)_72px_140px] items-center gap-3 rounded-xl px-2 py-3 text-sm transition-colors hover:bg-muted"
                                        >
                                            <RowNumber value={index + 1} />
                                            <span className="font-extrabold tabular-nums">
                                                {lesson.time}
                                            </span>
                                            <span className="truncate font-bold">
                                                {lesson.group}
                                            </span>
                                            <span className="truncate text-muted-foreground">
                                                {lesson.teacher ?? '—'}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {lesson.room ?? '—'}
                                            </span>
                                            <StatusBadge
                                                status={lesson.status}
                                                className="justify-self-start"
                                            />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
