import { Head } from '@inertiajs/react';
import { HandCoins } from 'lucide-react';
import { useState } from 'react';
import Field from '@/components/field';
import FormDialog from '@/components/form-dialog';
import HistoryTable from '@/components/history-table';
import type { History } from '@/components/history-table';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import PeriodFilter from '@/components/period-filter';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useCan } from '@/hooks/use-can';
import { formatDate, formatMoney, formatPhone } from '@/lib/format';
import { useT } from '@/lib/i18n';
import teachers from '@/routes/teachers';
import payouts from '@/routes/teachers/payouts';

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
    const [payoutBoxId, setPayoutBoxId] = useState(cashboxes[0]?.id);
    const payoutBoxBalance =
        cashboxes.find((c) => c.id === payoutBoxId)?.balance ?? 0;
    // Suggest the whole salary, but never more than the chosen cashbox holds.
    const suggestedPayout = Math.max(
        0,
        Math.min(teacher.balance, payoutBoxBalance),
    );
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
                            <FormDialog
                                title={t('Pay salary')}
                                description={t(
                                    'Available on balance: :amount',
                                    { amount: formatMoney(teacher.balance) },
                                )}
                                form={payouts.store.form(teacher.id)}
                                submitLabel={t('Pay')}
                                trigger={
                                    <Button className="bg-highlight text-highlight-foreground hover:bg-highlight/90">
                                        <HandCoins />
                                        {t('Pay salary')}
                                    </Button>
                                }
                            >
                                {(errors) => (
                                    <>
                                        <Field
                                            label={t('Cashbox')}
                                            error={errors.cashbox_id}
                                        >
                                            <NativeSelect
                                                name="cashbox_id"
                                                value={payoutBoxId}
                                                onChange={(e) =>
                                                    setPayoutBoxId(
                                                        Number(e.target.value),
                                                    )
                                                }
                                            >
                                                {cashboxes.map((c) => (
                                                    <option
                                                        key={c.id}
                                                        value={c.id}
                                                    >
                                                        {c.name} —{' '}
                                                        {formatMoney(c.balance)}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        </Field>
                                        <Field
                                            label={t('Amount')}
                                            error={errors.amount}
                                            hint={t('In cashbox: :amount', {
                                                amount: formatMoney(
                                                    payoutBoxBalance,
                                                ),
                                            })}
                                        >
                                            <Input
                                                key={payoutBoxId}
                                                name="amount"
                                                type="number"
                                                min={1}
                                                max={Math.min(
                                                    teacher.balance,
                                                    payoutBoxBalance,
                                                )}
                                                defaultValue={
                                                    suggestedPayout > 0
                                                        ? suggestedPayout
                                                        : undefined
                                                }
                                                required
                                            />
                                        </Field>
                                        <Field
                                            label={t('Note')}
                                            error={errors.note}
                                        >
                                            <Input name="note" />
                                        </Field>
                                    </>
                                )}
                            </FormDialog>
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
