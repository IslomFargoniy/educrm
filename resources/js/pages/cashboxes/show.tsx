import { Head } from '@inertiajs/react';
import CashActions from '@/components/cash-actions';
import HistoryTable from '@/components/history-table';
import type { History } from '@/components/history-table';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import PeriodFilter from '@/components/period-filter';
import { useT } from '@/lib/i18n';
import cashboxes from '@/routes/cashboxes';

type Props = {
    cashbox: {
        id: number;
        name: string;
        balance: number;
        method: string;
        branch: string;
        branch_id: number;
    };
    history: History;
    period: { from: string; to: string };
    freeProfit: number;
    categories: { id: number; name: string }[];
    cashboxes: {
        id: number;
        name: string;
        branch_id: number;
        balance: number;
    }[];
};

export default function CashboxShow({
    cashbox,
    history,
    period,
    freeProfit,
    categories,
    cashboxes: all,
}: Props) {
    const t = useT();

    return (
        <>
            <Head title={cashbox.name} />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={
                        <span>
                            {cashbox.name}{' '}
                            <Money
                                value={cashbox.balance}
                                className="ml-2 text-primary"
                            />
                        </span>
                    }
                    description={`${t(`method.${cashbox.method}`)} · ${cashbox.branch}`}
                    actions={
                        <CashActions
                            cashboxes={all}
                            fixedCashboxId={cashbox.id}
                            categories={categories}
                            freeProfit={freeProfit}
                        />
                    }
                />
                <div className="flex justify-end">
                    <PeriodFilter from={period.from} to={period.to} />
                </div>
                <HistoryTable
                    history={history}
                    context="cashbox"
                    exportName={`${cashbox.name} ${period.from} ${period.to}`}
                />
            </div>
        </>
    );
}

CashboxShow.layout = {
    breadcrumbs: [{ title: 'Cashboxes', href: cashboxes.index() }],
};
