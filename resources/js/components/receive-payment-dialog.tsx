import { Form } from '@inertiajs/react';
import { ArrowLeft, Banknote, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import Field from '@/components/field';
import Money from '@/components/money';
import MoneyInput from '@/components/money-input';
import PaymentGroup from '@/components/payment-group';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { formatMoney } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import enrollmentPayments from '@/routes/enrollments/payments';
import paymentRoutes from '@/routes/payments';

type Cashbox = { id: number; name: string; branch_id?: number };

type PayableEnrollment = {
    id: number;
    group: string;
    course: string | null;
    teacher: string | null;
    branch_id: number;
    balance: number;
    discounted_price: number | null;
};

type PayableStudent = {
    id: number;
    name: string;
    phone: string | null;
    enrollments: PayableEnrollment[];
};

/**
 * "Receive payment" from the cashbox page: find the student by name or phone,
 * pick the group (money is kept per group), then the same payment form as on the student's page.
 */
export default function ReceivePaymentDialog({
    cashboxes,
}: {
    cashboxes: Cashbox[];
}) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<PayableStudent[]>([]);
    const [searching, setSearching] = useState(false);
    const [student, setStudent] = useState<PayableStudent | null>(null);
    const [enrollment, setEnrollment] = useState<PayableEnrollment | null>(
        null,
    );

    useEffect(() => {
        const term = query.trim();
        if (term.length < 2) {
            setResults([]);

            return;
        }
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setSearching(true);
            fetch(paymentRoutes.students.url({ query: { q: term } }), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((response) => response.json())
                .then((body: { data: PayableStudent[] }) =>
                    setResults(body.data),
                )
                .catch(() => {})
                .finally(() => setSearching(false));
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    const reset = () => {
        setQuery('');
        setResults([]);
        setStudent(null);
        setEnrollment(null);
    };

    const choose = (picked: PayableStudent) => {
        setStudent(picked);
        // One group: go straight to the form; several: the cashier picks the group first.
        setEnrollment(
            picked.enrollments.length === 1 ? picked.enrollments[0] : null,
        );
    };

    const back = () => {
        if (enrollment && student && student.enrollments.length > 1) {
            setEnrollment(null);
        } else {
            setStudent(null);
            setEnrollment(null);
        }
    };

    const branchCashboxes = enrollment
        ? cashboxes.filter(
              (c) =>
                  c.branch_id === undefined ||
                  c.branch_id === enrollment.branch_id,
          )
        : [];

    const groupLine = (e: PayableEnrollment) =>
        [e.course, e.teacher].filter(Boolean).join(' · ');

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) {
                    reset();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button className="bg-highlight font-bold text-highlight-foreground hover:bg-highlight/90">
                    <Banknote />
                    {t('Receive payment')}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('Receive payment')}</DialogTitle>
                    <DialogDescription>
                        {student
                            ? enrollment
                                ? `${student.name} · ${t('Balance')}: ${formatMoney(enrollment.balance)}`
                                : `${student.name} · ${t('Which group is the payment for?')}`
                            : t('Find the student by name or phone')}
                    </DialogDescription>
                </DialogHeader>

                {!student && (
                    <div className="grid gap-3">
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                autoFocus
                                className="pl-9"
                                placeholder={t('Name or phone')}
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
                            {searching && (
                                <Spinner className="absolute top-1/2 right-3 -translate-y-1/2" />
                            )}
                        </div>
                        <div className="max-h-80 overflow-y-auto">
                            {results.map((s) => (
                                <button
                                    key={s.id}
                                    type="button"
                                    onClick={() => choose(s)}
                                    className="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left hover:bg-muted"
                                >
                                    <span className="min-w-0">
                                        <span className="block font-semibold">
                                            {s.name}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {[
                                                s.phone,
                                                ...s.enrollments.map(
                                                    (e) => e.group,
                                                ),
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    </span>
                                    <Money
                                        value={s.enrollments.reduce(
                                            (sum, e) => sum + e.balance,
                                            0,
                                        )}
                                        className="shrink-0 text-sm font-bold"
                                    />
                                </button>
                            ))}
                            {query.trim().length >= 2 &&
                                !searching &&
                                results.length === 0 && (
                                    <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                                        {t('No students found')}
                                    </p>
                                )}
                        </div>
                    </div>
                )}

                {student && !enrollment && (
                    <div className="grid gap-2">
                        {student.enrollments.map((e) => (
                            <button
                                key={e.id}
                                type="button"
                                onClick={() => setEnrollment(e)}
                                className="flex w-full items-center justify-between gap-3 rounded-lg border px-3 py-2.5 text-left transition-colors hover:border-primary hover:bg-muted/40"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate font-semibold">
                                        {e.group}
                                    </span>
                                    {groupLine(e) && (
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {groupLine(e)}
                                        </span>
                                    )}
                                </span>
                                <Money
                                    value={e.balance}
                                    className={cn(
                                        'shrink-0 text-sm font-bold',
                                        e.balance < 0 && 'text-destructive',
                                    )}
                                />
                            </button>
                        ))}
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={back}>
                                <ArrowLeft />
                                {t('Back')}
                            </Button>
                        </DialogFooter>
                    </div>
                )}

                {student && enrollment && (
                    <Form
                        key={enrollment.id}
                        {...enrollmentPayments.store.form(enrollment.id)}
                        transform={(data) => ({
                            ...data,
                            idempotency_key: crypto.randomUUID(),
                        })}
                        options={{ preserveScroll: true }}
                        onSuccess={() => {
                            setOpen(false);
                            reset();
                        }}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <PaymentGroup
                                    group={enrollment.group}
                                    course={enrollment.course}
                                    teacher={enrollment.teacher}
                                />
                                <Field label={t('Amount')} error={errors.amount}>
                                    <MoneyInput
                                        name="amount"
                                        min={1}
                                        required
                                        autoFocus
                                        defaultValue={
                                            enrollment.balance < 0
                                                ? -enrollment.balance
                                                : (enrollment.discounted_price ??
                                                  undefined)
                                        }
                                    />
                                </Field>
                                <Field
                                    label={t('Cashbox')}
                                    error={errors.cashbox_id}
                                >
                                    <NativeSelect name="cashbox_id" required>
                                        {branchCashboxes.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>
                                <Field label={t('Note')} error={errors.note}>
                                    <Input name="note" />
                                </Field>
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={back}
                                    >
                                        <ArrowLeft />
                                        {t('Back')}
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        {t('Receive')}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
