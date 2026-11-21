import { ChevronDown, ChevronRight, Download, Flag } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { toast } from 'sonner';
import Money from '@/components/money';
import { useShowMore } from '@/components/show-more';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { exportXlsx } from '@/lib/export-xlsx';
import { formatDate, formatDateTime } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type HistoryChild = {
    student: string;
    lesson_amount: number;
    covered: number;
    percent: number;
    share: number;
};

export type HistoryRow = {
    id: number | string;
    date: string;
    type: string;
    meta: Record<string, string | number | boolean | null>;
    income: number;
    expense: number;
    balance: number;
    note: string | null;
    by: string | null;
    flagged: boolean;
    reversed: boolean;
    children?: HistoryChild[];
};

export type History = {
    opening: number;
    income: number;
    expense: number;
    closing: number;
    rows: HistoryRow[];
};

type Context = 'cashbox' | 'teacher' | 'enrollment';

/**
 * Balance history: date · type · details · income · expense · balance after.
 */
export default function HistoryTable({
    history,
    context,
    labels,
    exportName,
}: {
    history: History;
    context: Context;
    labels?: { income?: string; expense?: string };
    /** File name for the Excel download (without extension). */
    exportName?: string;
}) {
    const t = useT();
    const [open, setOpen] = useState<Record<string, boolean>>({});
    const [type, setType] = useState('');
    const [direction, setDirection] = useState<'' | 'in' | 'out'>('');
    const [search, setSearch] = useState('');
    const [exporting, setExporting] = useState(false);
    const types = useMemo(
        () => [...new Set(history.rows.map((r) => r.type))],
        [history.rows],
    );
    const rows = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return history.rows.filter(
            (row) =>
                (type === '' || row.type === type) &&
                (direction === '' ||
                    (direction === 'in' ? row.income > 0 : row.expense > 0)) &&
                (needle === '' ||
                    [
                        describe(row, context, t),
                        row.note,
                        row.by,
                        ...(row.children ?? []).map((c) => c.student),
                    ]
                        .join(' ')
                        .toLowerCase()
                        .includes(needle)),
        );
    }, [history.rows, type, direction, search, context, t]);

    const { shown, button: moreButton } = useShowMore(rows);

    const download = async () => {
        setExporting(true);

        try {
            await exportXlsx(
                exportName ?? 'tarix',
                [
                    '№',
                    t('Date'),
                    t('Operation'),
                    t('Details'),
                    t('Income'),
                    t('Expense'),
                    t('Balance'),
                    t('By'),
                    t('Note'),
                ],
                rows.flatMap((row, index) => [
                    [
                        index + 1,
                        formatDateTime(row.date),
                        txTitle(row, t),
                        describe(row, context, t),
                        row.income || null,
                        row.expense || null,
                        row.balance,
                        row.by,
                        row.note,
                    ],
                    ...(row.children ?? []).map((c) => [
                        '',
                        '',
                        '',
                        `   ${c.student} · ${c.percent}%`,
                        c.share,
                        null,
                        null,
                        null,
                        null,
                    ]),
                ]),
            );
        } catch {
            toast.error(t('Could not download the file.'));
        } finally {
            setExporting(false);
        }
    };

    return (
        <div className="space-y-3">
            <div className="grid grid-cols-2 gap-2 md:grid-cols-4">
                <Summary label={t('Opening balance')} value={history.opening} />
                <Summary
                    label={labels?.income ?? t('Total income')}
                    value={history.income}
                    tone="success"
                />
                <Summary
                    label={labels?.expense ?? t('Total expense')}
                    value={history.expense}
                    tone="danger"
                />
                <Summary
                    label={t('Closing balance')}
                    value={history.closing}
                    strong
                />
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <NativeSelect
                    value={type}
                    onChange={(e) => setType(e.target.value)}
                    className="w-auto"
                    aria-label={t('Operation')}
                >
                    <option value="">{t('All operations')}</option>
                    {types.map((value) => (
                        <option key={value} value={value}>
                            {t(`tx.${value}`)}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    value={direction}
                    onChange={(e) =>
                        setDirection(e.target.value as '' | 'in' | 'out')
                    }
                    className="w-auto"
                    aria-label={t('Income')}
                >
                    <option value="">{t('Income and expense')}</option>
                    <option value="in">{t('Income only')}</option>
                    <option value="out">{t('Expense only')}</option>
                </NativeSelect>
                <Input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={t('Search')}
                    className="w-full sm:w-56"
                />
                <span className="text-xs text-muted-foreground">
                    {t(':shown of :total', {
                        shown: rows.length,
                        total: history.rows.length,
                    })}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    className="ml-auto"
                    disabled={exporting || rows.length === 0}
                    onClick={download}
                >
                    <Download />
                    Excel
                </Button>
            </div>

            <div className="overflow-x-auto rounded-xl border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-12 text-center">
                                №
                            </TableHead>
                            <TableHead>{t('Date')}</TableHead>
                            <TableHead>{t('Operation')}</TableHead>
                            <TableHead>{t('Details')}</TableHead>
                            <TableHead className="text-right">
                                {t('Income')}
                            </TableHead>
                            <TableHead className="text-right">
                                {t('Expense')}
                            </TableHead>
                            <TableHead className="text-right">
                                {t('Balance')}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={7}
                                    className="py-8 text-center text-muted-foreground"
                                >
                                    {t('No operations in this period.')}
                                </TableCell>
                            </TableRow>
                        )}
                        {shown.map((row, index) => {
                            const key = String(row.id);
                            const expandable =
                                Boolean(row.children?.length) ||
                                Boolean(row.note) ||
                                Boolean(row.by);

                            return (
                                <Fragment key={key}>
                                    <TableRow
                                        className={cn(
                                            expandable && 'cursor-pointer',
                                            row.reversed && 'opacity-60',
                                        )}
                                        onClick={() =>
                                            expandable &&
                                            setOpen({
                                                ...open,
                                                [key]: !open[key],
                                            })
                                        }
                                    >
                                        <TableCell className="w-12 text-center text-muted-foreground tabular-nums">
                                            {index + 1}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                            {formatDate(row.date)}
                                        </TableCell>
                                        <TableCell className="font-semibold whitespace-nowrap">
                                            <span className="inline-flex items-center gap-1">
                                                {expandable &&
                                                    (open[key] ? (
                                                        <ChevronDown className="size-3.5" />
                                                    ) : (
                                                        <ChevronRight className="size-3.5" />
                                                    ))}
                                                {txTitle(row, t)}
                                                {row.meta.is_substitute ===
                                                    true && (
                                                    <Badge
                                                        variant="outline"
                                                        className="ml-1"
                                                    >
                                                        {t('substitute')}
                                                    </Badge>
                                                )}
                                                {row.flagged && (
                                                    <Flag className="size-3.5 text-highlight" />
                                                )}
                                                {row.reversed && (
                                                    <Badge
                                                        variant="outline"
                                                        className="ml-1"
                                                    >
                                                        {t('cancelled')}
                                                    </Badge>
                                                )}
                                            </span>
                                        </TableCell>
                                        <TableCell className="min-w-56 text-sm">
                                            {describe(row, context, t)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {row.income > 0 ? (
                                                <Money
                                                    value={row.income}
                                                    className="font-semibold text-success"
                                                />
                                            ) : (
                                                ''
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {row.expense > 0 ? (
                                                <Money
                                                    value={row.expense}
                                                    className="font-semibold text-danger"
                                                />
                                            ) : row.type.endsWith('_lesson') ? (
                                                '—'
                                            ) : (
                                                ''
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Money
                                                value={row.balance}
                                                signed={
                                                    context === 'enrollment'
                                                }
                                                className="font-semibold"
                                            />
                                        </TableCell>
                                    </TableRow>
                                    {open[key] && (
                                        <TableRow className="bg-muted/40 hover:bg-muted/40">
                                            <TableCell
                                                colSpan={7}
                                                className="space-y-2 text-sm"
                                            >
                                                {row.children &&
                                                    row.children.length > 0 && (
                                                        <table className="w-full max-w-xl text-sm">
                                                            <thead className="text-xs text-muted-foreground">
                                                                <tr>
                                                                    <th className="py-1 text-left">
                                                                        {t(
                                                                            'Student',
                                                                        )}
                                                                    </th>
                                                                    <th className="py-1 text-right">
                                                                        {t(
                                                                            'Lesson price',
                                                                        )}
                                                                    </th>
                                                                    <th className="py-1 text-right">
                                                                        {t(
                                                                            'Percent',
                                                                        )}
                                                                    </th>
                                                                    <th className="py-1 text-right">
                                                                        {t(
                                                                            'Share',
                                                                        )}
                                                                    </th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {row.children.map(
                                                                    (
                                                                        child,
                                                                        index,
                                                                    ) => (
                                                                        <tr
                                                                            key={
                                                                                index
                                                                            }
                                                                        >
                                                                            <td className="py-1">
                                                                                {
                                                                                    child.student
                                                                                }
                                                                            </td>
                                                                            <td className="py-1 text-right">
                                                                                <Money
                                                                                    value={
                                                                                        child.lesson_amount
                                                                                    }
                                                                                />
                                                                            </td>
                                                                            <td className="py-1 text-right">
                                                                                {
                                                                                    child.percent
                                                                                }
                                                                                %
                                                                            </td>
                                                                            <td className="py-1 text-right font-semibold">
                                                                                <Money
                                                                                    value={
                                                                                        child.share
                                                                                    }
                                                                                />
                                                                            </td>
                                                                        </tr>
                                                                    ),
                                                                )}
                                                            </tbody>
                                                        </table>
                                                    )}
                                                <div className="flex flex-wrap gap-x-6 gap-y-1 text-muted-foreground">
                                                    <span>
                                                        {formatDateTime(
                                                            row.date,
                                                        )}
                                                    </span>
                                                    {row.by && (
                                                        <span>
                                                            {t('By')}: {row.by}
                                                        </span>
                                                    )}
                                                    {row.note && (
                                                        <span>
                                                            {t('Note')}:{' '}
                                                            {row.note}
                                                        </span>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </Fragment>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>
            {moreButton}
        </div>
    );
}

function Summary({
    label,
    value,
    tone,
    strong,
}: {
    label: string;
    value: number;
    tone?: 'success' | 'danger';
    strong?: boolean;
}) {
    return (
        <div
            className={cn(
                'rounded-xl border bg-card px-3 py-2',
                strong && 'border-primary/40',
            )}
        >
            <div className="text-[11px] font-semibold text-muted-foreground uppercase">
                {label}
            </div>
            <Money
                value={value}
                className={cn(
                    'text-lg font-extrabold',
                    tone === 'success' && 'text-success',
                    tone === 'danger' && 'text-danger',
                )}
            />
        </div>
    );
}

/**
 * Row title: a reversal with a known reason shows the reason itself (e.g. "Sababli qilib belgilandi")
 * instead of the generic "Ulush qaytarildi" / "Dars qayta hisoblandi".
 */
function txTitle(row: HistoryRow, t: (key: string) => string): string {
    const reason = row.meta?.reason;
    if ((row.type === 'earning_reversal' || row.type === 'charge_reversal') && typeof reason === 'string' && reason !== '') {
        return t(`tx_reason.${reason}`);
    }

    return t(`tx.${row.type}`);
}

function describe(
    row: HistoryRow,
    context: Context,
    t: (
        key: string,
        params?: Record<string, string | number | boolean | null | undefined>,
    ) => string,
): string {
    const m = row.meta;
    const lessonDate = m.lesson_date ? formatDate(String(m.lesson_date)) : '';
    const who = [m.student, m.group].filter(Boolean).join(' — ');

    switch (row.type) {
        case 'student_payment':
        case 'payment_reversal':
            return context === 'enrollment'
                ? `${m.cashbox ?? ''} · № ${m.receipt_no ?? ''}`
                : `${who} · № ${m.receipt_no ?? ''}`;
        case 'lesson_charge':
            return t(':attendance · lesson :seq of :total (:date)', {
                attendance: t(`attendance.${m.attendance}`),
                seq: m.seq,
                total: m.lessons_per_month,
                date: lessonDate,
            });
        case 'charge_reversal':
            return context === 'enrollment'
                ? t('Lesson :date re-billed or cancelled', { date: lessonDate })
                : `${who}, ${lessonDate}`;
        case 'lesson_share':
            return t(':group, lesson :date — :count student(s)', {
                group: m.group,
                date: lessonDate,
                count: m.students,
            });
        case 'teacher_earning':
            return t(':student paid → :group, lesson :date', {
                student: m.student,
                group: m.group,
                date: lessonDate,
            });
        case 'earning_reversal':
            return t(':student — :group, lesson :date: share taken back', {
                student: m.student,
                group: m.group,
                date: lessonDate,
            });
        case 'teacher_payout':
            return context === 'teacher'
                ? String(m.cashbox ?? '')
                : String(m.teacher ?? '');
        case 'expense':
            return [m.category, row.note].filter(Boolean).join(' — ');
        case 'owner_withdrawal':
            return row.flagged
                ? t('More than the free profit')
                : String(row.note ?? '');
        case 'cashbox_transfer':
            return row.income > 0
                ? t('from :name', { name: m.from_cashbox })
                : t('to :name', { name: m.to_cashbox });
        case 'balance_transfer':
            if (context === 'enrollment') {
                return row.income > 0
                    ? t('Moved from :group', { group: m.from_group })
                    : t('Moved to :group', { group: m.to_group });
            }

            return `${m.student}: ${m.from_group} → ${m.to_group}`;
        case 'refund':
            return context === 'enrollment' ? String(m.cashbox ?? '') : who;
        case 'debt_writeoff':
            return context === 'enrollment' ? String(row.note ?? '') : who;
        case 'opening_balance':
            return t('Balance from the old system');
        case 'free_lesson':
            return t(':date · :attendance · free (over the monthly limit)', {
                date: lessonDate,
                attendance: t(`attendance.${m.attendance}`),
            });
        case 'excused_lesson':
            return t(':date · excused · not billed', { date: lessonDate });
        default:
            return who;
    }
}
