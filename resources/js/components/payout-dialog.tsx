import { HandCoins } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import Field from '@/components/field';
import FormDialog from '@/components/form-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { formatMoney } from '@/lib/format';
import { useT } from '@/lib/i18n';
import payouts from '@/routes/teachers/payouts';

export type PayoutCashbox = { id: number; name: string; balance: number };

/**
 * "Pay salary" dialog: the suggested amount is the teacher's balance,
 * but never more than the chosen cashbox holds.
 */
export default function PayoutDialog({
    teacher,
    cashboxes,
    trigger,
}: {
    teacher: { id: number; name: string; balance: number };
    cashboxes: PayoutCashbox[];
    trigger?: ReactNode;
}) {
    const t = useT();
    const [cashboxId, setCashboxId] = useState(cashboxes[0]?.id);
    const cashboxBalance =
        cashboxes.find((c) => c.id === cashboxId)?.balance ?? 0;
    const maxAmount = Math.max(0, Math.min(teacher.balance, cashboxBalance));

    return (
        <FormDialog
            title={`${t('Pay salary')}: ${teacher.name}`}
            description={t('Available on balance: :amount', {
                amount: formatMoney(teacher.balance),
            })}
            form={payouts.store.form(teacher.id)}
            submitLabel={t('Pay')}
            trigger={
                trigger ?? (
                    <Button className="bg-highlight text-highlight-foreground hover:bg-highlight/90">
                        <HandCoins />
                        {t('Pay salary')}
                    </Button>
                )
            }
        >
            {(errors) => (
                <>
                    <Field label={t('Cashbox')} error={errors.cashbox_id}>
                        <NativeSelect
                            name="cashbox_id"
                            value={cashboxId}
                            onChange={(e) =>
                                setCashboxId(Number(e.target.value))
                            }
                        >
                            {cashboxes.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name} — {formatMoney(c.balance)}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <Field
                        label={t('Amount')}
                        error={errors.amount}
                        hint={t('In cashbox: :amount', {
                            amount: formatMoney(cashboxBalance),
                        })}
                    >
                        <Input
                            key={cashboxId}
                            name="amount"
                            type="number"
                            min={1}
                            max={maxAmount}
                            defaultValue={maxAmount > 0 ? maxAmount : undefined}
                            required
                        />
                    </Field>
                    <Field label={t('Note')} error={errors.note}>
                        <Input name="note" />
                    </Field>
                </>
            )}
        </FormDialog>
    );
}
