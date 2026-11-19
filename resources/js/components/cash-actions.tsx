import { Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    HandCoins,
    MinusCircle,
    PiggyBank,
} from 'lucide-react';
import { useState } from 'react';
import Field from '@/components/field';
import FormDialog from '@/components/form-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import MoneyInput from '@/components/money-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { useCan } from '@/hooks/use-can';
import { formatMoney } from '@/lib/format';
import { useT } from '@/lib/i18n';
import cashboxTransfers from '@/routes/cashbox-transfers';
import teachers from '@/routes/teachers';
import expenses from '@/routes/expenses';
import withdrawals from '@/routes/withdrawals';

type Cashbox = {
    id: number;
    name: string;
    branch_id?: number;
    balance: number;
};

/**
 * Expense, profit withdrawal and transfer buttons for cashbox pages.
 */
export default function CashActions({
    cashboxes,
    fixedCashboxId,
    categories,
    freeProfit,
}: {
    cashboxes: Cashbox[];
    fixedCashboxId?: number;
    categories: { id: number; name: string }[];
    freeProfit: number;
}) {
    const t = useT();
    const { can } = useCan();
    const [overFree, setOverFree] = useState(false);
    const firstId = fixedCashboxId ?? cashboxes[0]?.id;
    const [expenseBoxId, setExpenseBoxId] = useState(firstId);
    const [transferBoxId, setTransferBoxId] = useState(firstId);
    const [withdrawBoxId, setWithdrawBoxId] = useState(firstId);
    const balanceOf = (id?: number) =>
        cashboxes.find((c) => c.id === id)?.balance ?? 0;
    const inCashbox = (id?: number) =>
        t('In cashbox: :amount', { amount: formatMoney(balanceOf(id)) });
    // Suggest the free profit, but never more than the chosen cashbox holds.
    const suggestedWithdrawal = Math.max(
        0,
        Math.min(freeProfit, balanceOf(withdrawBoxId)),
    );

    const cashboxField = (
        error: string | undefined,
        value: number | undefined,
        onChange: (id: number) => void,
    ) =>
        fixedCashboxId ? (
            <input type="hidden" name="cashbox_id" value={fixedCashboxId} />
        ) : (
            <Field label={t('Cashbox')} error={error}>
                <NativeSelect
                    name="cashbox_id"
                    required
                    value={value}
                    onChange={(e) => onChange(Number(e.target.value))}
                >
                    {cashboxes.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name} — {formatMoney(c.balance)}
                        </option>
                    ))}
                </NativeSelect>
            </Field>
        );

    return (
        <>
            {can('payouts.create') && (
                <Button
                    asChild
                    className="bg-highlight text-highlight-foreground hover:bg-highlight/90"
                >
                    <Link href={teachers.index()}>
                        <HandCoins />
                        {t('Teacher salaries')}
                    </Link>
                </Button>
            )}
            {can('expenses.create') && (
                <FormDialog
                    title={t('Expense')}
                    form={expenses.store.form()}
                    trigger={
                        <Button variant="outline">
                            <MinusCircle />
                            {t('Expense')}
                        </Button>
                    }
                >
                    {(errors) => (
                        <>
                            {cashboxField(
                                errors.cashbox_id,
                                expenseBoxId,
                                setExpenseBoxId,
                            )}
                            <Field
                                label={t('Category')}
                                error={errors.expense_category_id}
                                hint={t(
                                    'Teacher salaries are paid on the Teachers page, not as an expense.',
                                )}
                            >
                                <NativeSelect
                                    name="expense_category_id"
                                    required
                                >
                                    {categories.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field
                                label={t('Amount')}
                                error={errors.amount}
                                hint={inCashbox(expenseBoxId)}
                            >
                                <MoneyInput
                                    name="amount"
                                    min={1}
                                    max={balanceOf(expenseBoxId)}
                                    required
                                />
                            </Field>
                            <Field label={t('Note')} error={errors.note}>
                                <Input
                                    name="note"
                                    placeholder={t('e.g. banner on the street')}
                                />
                            </Field>
                        </>
                    )}
                </FormDialog>
            )}
            {can('cashboxes.transfer') && cashboxes.length > 1 && (
                <FormDialog
                    title={t('Move money between cashboxes')}
                    form={cashboxTransfers.store.form()}
                    trigger={
                        <Button variant="outline">
                            <ArrowLeftRight />
                            {t('Transfer')}
                        </Button>
                    }
                >
                    {(errors) => (
                        <>
                            {cashboxField(
                                errors.cashbox_id,
                                transferBoxId,
                                setTransferBoxId,
                            )}
                            <Field
                                label={t('To cashbox')}
                                error={errors.to_cashbox_id}
                            >
                                <NativeSelect name="to_cashbox_id" required>
                                    {cashboxes
                                        .filter(
                                            (c) =>
                                                c.id !==
                                                (fixedCashboxId ??
                                                    transferBoxId),
                                        )
                                        .map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                </NativeSelect>
                            </Field>
                            <Field
                                label={t('Amount')}
                                error={errors.amount}
                                hint={inCashbox(transferBoxId)}
                            >
                                <MoneyInput
                                    name="amount"
                                    min={1}
                                    max={balanceOf(transferBoxId)}
                                    required
                                />
                            </Field>
                            <Field label={t('Note')} error={errors.note}>
                                <Input name="note" />
                            </Field>
                        </>
                    )}
                </FormDialog>
            )}
            {can('profit.withdraw') && (
                <FormDialog
                    title={t('Withdraw profit')}
                    description={t(
                        'Free profit: :amount (cash minus teacher money and prepaid student money).',
                        { amount: formatMoney(freeProfit) },
                    )}
                    form={withdrawals.store.form()}
                    transform={(data) => ({ ...data, confirm: overFree })}
                    trigger={
                        <Button variant="outline">
                            <PiggyBank />
                            {t('Withdraw profit')}
                        </Button>
                    }
                >
                    {(errors) => (
                        <>
                            {cashboxField(
                                errors.cashbox_id,
                                withdrawBoxId,
                                setWithdrawBoxId,
                            )}
                            <Field
                                label={t('Amount')}
                                error={errors.amount}
                                hint={inCashbox(withdrawBoxId)}
                            >
                                <MoneyInput
                                    key={withdrawBoxId}
                                    name="amount"
                                    min={1}
                                    defaultValue={
                                        suggestedWithdrawal > 0
                                            ? suggestedWithdrawal
                                            : undefined
                                    }
                                    required
                                />
                            </Field>
                            <Field label={t('Note')} error={errors.note}>
                                <Input name="note" />
                            </Field>
                            {errors.confirm && (
                                <div className="space-y-2 rounded-lg border border-highlight bg-highlight/10 p-3 text-sm">
                                    <p>{errors.confirm}</p>
                                    <Label className="flex items-center gap-2">
                                        <Checkbox
                                            checked={overFree}
                                            onCheckedChange={(v) =>
                                                setOverFree(v === true)
                                            }
                                        />
                                        {t('I understand, withdraw anyway')}
                                    </Label>
                                </div>
                            )}
                        </>
                    )}
                </FormDialog>
            )}
        </>
    );
}
