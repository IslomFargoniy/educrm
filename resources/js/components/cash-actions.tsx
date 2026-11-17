import { ArrowLeftRight, MinusCircle, PiggyBank } from 'lucide-react';
import { useState } from 'react';
import Field from '@/components/field';
import FormDialog from '@/components/form-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { useCan } from '@/hooks/use-can';
import { formatMoney } from '@/lib/format';
import { useT } from '@/lib/i18n';
import cashboxTransfers from '@/routes/cashbox-transfers';
import expenses from '@/routes/expenses';
import withdrawals from '@/routes/withdrawals';

type Cashbox = { id: number; name: string; branch_id?: number };

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
    const cashboxField = (error?: string) =>
        fixedCashboxId ? (
            <input type="hidden" name="cashbox_id" value={fixedCashboxId} />
        ) : (
            <Field label={t('Cashbox')} error={error}>
                <NativeSelect name="cashbox_id" required>
                    {cashboxes.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name}
                        </option>
                    ))}
                </NativeSelect>
            </Field>
        );

    return (
        <>
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
                            {cashboxField(errors.cashbox_id)}
                            <Field
                                label={t('Category')}
                                error={errors.expense_category_id}
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
                            <Field label={t('Amount')} error={errors.amount}>
                                <Input
                                    name="amount"
                                    type="number"
                                    min={1}
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
                            {cashboxField(errors.cashbox_id)}
                            <Field
                                label={t('To cashbox')}
                                error={errors.to_cashbox_id}
                            >
                                <NativeSelect name="to_cashbox_id" required>
                                    {cashboxes
                                        .filter((c) => c.id !== fixedCashboxId)
                                        .map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                </NativeSelect>
                            </Field>
                            <Field label={t('Amount')} error={errors.amount}>
                                <Input
                                    name="amount"
                                    type="number"
                                    min={1}
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
                            {cashboxField(errors.cashbox_id)}
                            <Field label={t('Amount')} error={errors.amount}>
                                <Input
                                    name="amount"
                                    type="number"
                                    min={1}
                                    defaultValue={
                                        freeProfit > 0 ? freeProfit : undefined
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
