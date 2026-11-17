import { Head } from '@inertiajs/react';
import { useState } from 'react';
import EmptyState from '@/components/empty-state';
import HistoryTable from '@/components/history-table';
import type { History } from '@/components/history-table';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import { currentMonth, formatDays, formatMonth } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { portal } from '@/routes';

type Child = {
    id: number;
    full_name: string;
    groups: {
        id: number;
        group: string;
        teacher: string | null;
        days: number[];
        time: string;
        balance: number;
        monthly_price: number | null;
        billed_this_month: number;
        lessons_per_month: number | null;
        history: History;
    }[];
};

export default function Portal({ children }: { children: Child[] }) {
    const t = useT();
    const locale = useLocale();
    const [openGroup, setOpenGroup] = useState<number | null>(null);

    return (
        <>
            <Head title={t('My children')} />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={t('My children')}
                    description={t(
                        'Balances, lessons and payments. Negative balance — debt.',
                    )}
                />
                {children.length === 0 && (
                    <EmptyState>
                        {t('No students are linked to your account yet.')}
                    </EmptyState>
                )}
                {children.map((child) => (
                    <section key={child.id} className="space-y-3">
                        <h2 className="text-xl font-extrabold">
                            {child.full_name}
                        </h2>
                        <div className="grid gap-3 md:grid-cols-2">
                            {child.groups.map((g) => (
                                <button
                                    key={g.id}
                                    type="button"
                                    onClick={() =>
                                        setOpenGroup(
                                            openGroup === g.id ? null : g.id,
                                        )
                                    }
                                    className={cn(
                                        'rounded-2xl border bg-card p-4 text-left transition-colors hover:border-primary',
                                        openGroup === g.id && 'border-primary',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <div className="text-lg font-bold">
                                                {g.group}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {g.teacher} ·{' '}
                                                {formatDays(g.days, locale)}{' '}
                                                {g.time}
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <Money
                                                value={g.balance}
                                                signed
                                                className="text-xl"
                                            />
                                            <div className="text-xs text-muted-foreground">
                                                {g.balance < 0
                                                    ? t('debt')
                                                    : t('balance')}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="mt-3 flex justify-between border-t pt-2 text-sm text-muted-foreground">
                                        <span>
                                            {formatMonth(
                                                currentMonth(),
                                                locale,
                                            )}
                                            : {g.billed_this_month} /{' '}
                                            {g.lessons_per_month ?? '—'}{' '}
                                            {t('lessons')}
                                        </span>
                                        <span>
                                            {t('Monthly')}:{' '}
                                            <Money value={g.monthly_price} />
                                        </span>
                                    </div>
                                </button>
                            ))}
                        </div>
                        {child.groups
                            .filter((g) => g.id === openGroup)
                            .map((g) => (
                                <HistoryTable
                                    key={g.id}
                                    history={g.history}
                                    context="enrollment"
                                    labels={{
                                        income: t('Total paid'),
                                        expense: t('Total for lessons'),
                                    }}
                                />
                            ))}
                    </section>
                ))}
            </div>
        </>
    );
}

Portal.layout = {
    breadcrumbs: [{ title: 'My children', href: portal() }],
};
