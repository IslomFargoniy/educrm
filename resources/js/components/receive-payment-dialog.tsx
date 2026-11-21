import { Form } from "@inertiajs/react";
import { ArrowLeft, Banknote, Search } from "lucide-react";
import { useEffect, useState } from "react";
import Field from "@/components/field";
import Money from "@/components/money";
import MoneyInput from "@/components/money-input";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { NativeSelect } from "@/components/ui/native-select";
import { Spinner } from "@/components/ui/spinner";
import { formatMoney } from "@/lib/format";
import { useT } from "@/lib/i18n";
import enrollmentPayments from "@/routes/enrollments/payments";
import paymentRoutes from "@/routes/payments";

type Cashbox = { id: number; name: string; branch_id?: number };

type PayableEnrollment = {
    id: number;
    group: string;
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
 * pick the group, then the same payment form as on the student's page.
 */
export default function ReceivePaymentDialog({
    cashboxes,
}: {
    cashboxes: Cashbox[];
}) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState("");
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
                headers: { Accept: "application/json" },
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
        setQuery("");
        setResults([]);
        setStudent(null);
        setEnrollment(null);
    };

    const choose = (picked: PayableStudent) => {
        setStudent(picked);
        setEnrollment(picked.enrollments[0] ?? null);
    };

    const branchCashboxes = enrollment
        ? cashboxes.filter(
              (c) =>
                  c.branch_id === undefined ||
                  c.branch_id === enrollment.branch_id,
          )
        : [];

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
                    {t("Receive payment")}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t("Receive payment")}</DialogTitle>
                    <DialogDescription>
                        {student
                            ? `${student.name}${enrollment ? ` · ${t("Balance")}: ${formatMoney(enrollment.balance)}` : ""}`
                            : t("Find the student by name or phone")}
                    </DialogDescription>
                </DialogHeader>

                {!student ? (
                    <div className="grid gap-3">
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                autoFocus
                                className="pl-9"
                                placeholder={t("Name or phone")}
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
                                    <span>
                                        <span className="block font-semibold">
                                            {s.name}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {[
                                                s.phone,
                                                ...s.enrollments.map(
                                                    (e) => e.group,
                                                ),
                                            ]
                                                .filter(Boolean)
                                                .join(" · ")}
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
                                        {t("No students found")}
                                    </p>
                                )}
                        </div>
                    </div>
                ) : (
                    enrollment && (
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
                                    {student.enrollments.length > 1 && (
                                        <Field label={t("Group")}>
                                            <NativeSelect
                                                value={enrollment.id}
                                                onChange={(e) =>
                                                    setEnrollment(
                                                        student.enrollments.find(
                                                            (x) =>
                                                                x.id ===
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                        ) ?? null,
                                                    )
                                                }
                                            >
                                                {student.enrollments.map(
                                                    (e) => (
                                                        <option
                                                            key={e.id}
                                                            value={e.id}
                                                        >
                                                            {e.group} —{" "}
                                                            {formatMoney(
                                                                e.balance,
                                                            )}
                                                        </option>
                                                    ),
                                                )}
                                            </NativeSelect>
                                        </Field>
                                    )}
                                    <Field
                                        label={t("Amount")}
                                        error={errors.amount}
                                    >
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
                                        label={t("Cashbox")}
                                        error={errors.cashbox_id}
                                    >
                                        <NativeSelect
                                            name="cashbox_id"
                                            required
                                        >
                                            {branchCashboxes.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {c.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </Field>
                                    <Field
                                        label={t("Note")}
                                        error={errors.note}
                                    >
                                        <Input name="note" />
                                    </Field>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() => {
                                                setStudent(null);
                                                setEnrollment(null);
                                            }}
                                        >
                                            <ArrowLeft />
                                            {t("Back")}
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            {t("Receive")}
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )
                )}
            </DialogContent>
        </Dialog>
    );
}
