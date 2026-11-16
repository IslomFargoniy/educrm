import { Head, Link } from '@inertiajs/react';
import CashActions from '@/components/cash-actions';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { formatMoney } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import cashboxes from '@/routes/cashboxes';

type Props = {
    cashboxes: { id: number; name: string; balance: number; is_active: boolean; method: string; branch: string; branch_id: number }[];
    summary: { cash: number; teacherLiability: number; prepaid: number; freeProfit: number };
    categories: { id: number; name: string }[];
};

export default function CashboxesIndex({ cashboxes: list, summary, categories }: Props) {
    const t = useT();

    return (
        <>
            <Head title={t('Cashboxes')} />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={t('Cashboxes')}
                    actions={<CashActions cashboxes={list.filter((c) => c.is_active)} categories={categories} freeProfit={summary.freeProfit} />}
                />
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard tone="primary" label={t('Cash in all cashboxes')} value={formatMoney(summary.cash)} />
                    <StatCard label={t('Owed to teachers')} value={formatMoney(summary.teacherLiability)} />
                    <StatCard label={t('Paid in advance')} value={formatMoney(summary.prepaid)} />
                    <StatCard tone="success" label={t('Free profit')} value={formatMoney(summary.freeProfit)} />
                </div>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {list.map((c) => (
                        <Link key={c.id} href={cashboxes.show(c.id)} className={cn('rounded-2xl border bg-card p-4 transition-colors hover:border-primary', !c.is_active && 'opacity-50')}>
                            <div className="text-xs font-semibold text-muted-foreground uppercase">{t(`method.${c.method}`)} · {c.branch}</div>
                            <div className="mt-1 font-bold">{c.name}</div>
                            <Money value={c.balance} className="mt-2 block text-2xl font-extrabold" />
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}

CashboxesIndex.layout = {
    breadcrumbs: [{ title: 'Cashboxes', href: cashboxes.index() }],
};
