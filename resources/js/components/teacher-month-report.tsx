import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Download } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import Money from '@/components/money';
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
import { formatMonth, shiftMonth } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type ReportStudent = {
    enrollment_id: number;
    student_id: number;
    student: string;
    phone: string | null;
    status: string;
    present: number;
    absent: number;
    excused: number;
    billed: number;
    paid: number;
    unpaid: number;
    charged: number;
    covered: number;
    debt: number;
    share_received: number;
    share_waiting: number;
    balance: number;
};

export type ReportGroup = {
    id: number;
    name: string;
    lessons_per_month: number;
    lessons_held: number;
    percent: number;
    students: ReportStudent[];
    totals: Record<string, number>;
};

type Status = '' | 'debt' | 'paid' | 'absent';

/**
 * Teacher's month by group and student: attended of the plan, paid / unpaid lessons, share received / waiting.
 */
export default function TeacherMonthReport({
    teacherName,
    month,
    groups,
}: {
    teacherName: string;
    month: string;
    groups: ReportGroup[];
}) {
    const t = useT();
    const locale = useLocale();
    const [groupId, setGroupId] = useState('');
    const [status, setStatus] = useState<Status>('');
    const [search, setSearch] = useState('');
    const [exporting, setExporting] = useState(false);

    const visible = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return groups
            .filter((g) => groupId === '' || String(g.id) === groupId)
            .map((g) => ({
                ...g,
                students: g.students.filter(
                    (s) =>
                        (needle === '' ||
                            s.student.toLowerCase().includes(needle)) &&
                        (status === '' ||
                            (status === 'debt' && s.unpaid > 0) ||
                            (status === 'paid' &&
                                s.billed > 0 &&
                                s.unpaid === 0) ||
                            (status === 'absent' && s.absent > 0)),
                ),
            }))
            .filter(
                (g) =>
                    g.students.length > 0 || (status === '' && needle === ''),
            );
    }, [groups, groupId, status, search]);

    const goMonth = (value: string) =>
        router.get(
            window.location.pathname,
            {
                ...Object.fromEntries(
                    new URLSearchParams(window.location.search),
                ),
                month: value,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['report', 'month'],
            },
        );

    const download = async () => {
        setExporting(true);

        try {
            await exportXlsx(
                `${teacherName} ${month}`,
                [
                    t('Group'),
                    '№',
                    t('Student'),
                    t('Lessons in the month'),
                    t('Came'),
                    t('Absent'),
                    t('Excused'),
                    t('Billed lessons'),
                    t('Paid lessons'),
                    t('Unpaid lessons'),
                    t('Charged'),
                    t('Paid'),
                    t('Debt'),
                    t('Teacher share received'),
                    t('Teacher share waiting'),
                ],
                visible.flatMap((g) =>
                    g.students.map((s, index) => [
                        g.name,
                        index + 1,
                        s.student,
                        g.lessons_per_month,
                        s.present,
                        s.absent,
                        s.excused,
                        s.billed,
                        s.paid,
                        s.unpaid,
                        s.charged,
                        s.covered,
                        s.debt,
                        s.share_received,
                        s.share_waiting,
                    ]),
                ),
            );
        } catch {
            toast.error(t('Could not download the file.'));
        } finally {
            setExporting(false);
        }
    };

    return (
        <section className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
                <h2 className="mr-auto text-lg font-bold">
                    {t('Month by groups and students')}
                </h2>
                <div className="flex items-center gap-1 rounded-lg border bg-card p-1">
                    <Button
                        size="icon"
                        variant="ghost"
                        className="size-7"
                        aria-label={t('Previous month')}
                        onClick={() => goMonth(shiftMonth(month, -1))}
                    >
                        <ChevronLeft />
                    </Button>
                    <input
                        type="month"
                        value={month}
                        onChange={(e) =>
                            e.target.value && goMonth(e.target.value)
                        }
                        className="bg-transparent px-1 text-sm font-semibold capitalize"
                        aria-label={formatMonth(month, locale)}
                    />
                    <Button
                        size="icon"
                        variant="ghost"
                        className="size-7"
                        aria-label={t('Next month')}
                        onClick={() => goMonth(shiftMonth(month, 1))}
                    >
                        <ChevronRight />
                    </Button>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <NativeSelect
                    value={groupId}
                    onChange={(e) => setGroupId(e.target.value)}
                    className="w-auto"
                    aria-label={t('Group')}
                >
                    <option value="">{t('All groups')}</option>
                    {groups.map((g) => (
                        <option key={g.id} value={g.id}>
                            {g.name}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    value={status}
                    onChange={(e) => setStatus(e.target.value as Status)}
                    className="w-auto"
                    aria-label={t('Status')}
                >
                    <option value="">{t('All students')}</option>
                    <option value="debt">{t('Has unpaid lessons')}</option>
                    <option value="paid">{t('All lessons paid')}</option>
                    <option value="absent">{t('Missed lessons')}</option>
                </NativeSelect>
                <Input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={t('Search')}
                    className="w-full sm:w-56"
                />
                <Button
                    variant="outline"
                    size="sm"
                    className="ml-auto"
                    disabled={exporting}
                    onClick={download}
                >
                    <Download />
                    Excel
                </Button>
            </div>

            {visible.length === 0 && (
                <div className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                    {t('No data.')}
                </div>
            )}

            {visible.map((g) => (
                <div
                    key={g.id}
                    className="overflow-hidden rounded-xl border bg-card"
                >
                    <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1 border-b px-4 py-3">
                        <span className="font-bold">{g.name}</span>
                        <span className="text-xs text-muted-foreground">
                            {t(
                                ':held of :plan lessons held · teacher :percent%',
                                {
                                    held: g.lessons_held,
                                    plan: g.lessons_per_month,
                                    percent: g.percent,
                                },
                            )}
                        </span>
                    </div>
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10 text-center">
                                        №
                                    </TableHead>
                                    <TableHead>{t('Student')}</TableHead>
                                    <TableHead className="text-center">
                                        {t('Came')}
                                    </TableHead>
                                    <TableHead className="text-center">
                                        {t('Paid lessons')}
                                    </TableHead>
                                    <TableHead className="text-center">
                                        {t('Unpaid lessons')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Charged')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Debt')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Share received')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Share waiting')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {g.students.map((s, index) => (
                                    <TableRow
                                        key={s.enrollment_id}
                                        className={cn(
                                            !['active', 'trial'].includes(
                                                s.status,
                                            ) && 'opacity-60',
                                        )}
                                    >
                                        <TableCell className="text-center text-muted-foreground tabular-nums">
                                            {index + 1}
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            {s.student}
                                            {s.status === 'trial' && (
                                                <span className="ml-2 text-xs text-highlight">
                                                    {t('trial')}
                                                </span>
                                            )}
                                            {s.status === 'left' && (
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    {t('left')}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-center tabular-nums">
                                            <span className="font-bold">
                                                {s.present}
                                            </span>
                                            <span className="text-muted-foreground">
                                                /{g.lessons_per_month}
                                            </span>
                                            {(s.absent > 0 ||
                                                s.excused > 0) && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {s.absent > 0 &&
                                                        t(':n absent', {
                                                            n: s.absent,
                                                        })}
                                                    {s.absent > 0 &&
                                                        s.excused > 0 &&
                                                        ' · '}
                                                    {s.excused > 0 &&
                                                        t(':n excused', {
                                                            n: s.excused,
                                                        })}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-center font-semibold text-success tabular-nums">
                                            {s.paid}
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                'text-center font-semibold tabular-nums',
                                                s.unpaid > 0 && 'text-danger',
                                            )}
                                        >
                                            {s.unpaid}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Money value={s.charged} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Money value={-s.debt} signed />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Money
                                                value={s.share_received}
                                                className="text-success"
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Money
                                                value={-s.share_waiting}
                                                signed
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                            {g.students.length > 1 && (
                                <tfoot className="border-t bg-muted/40">
                                    <TableRow>
                                        <TableCell />
                                        <TableCell className="font-bold">
                                            {t('Total')}
                                        </TableCell>
                                        <TableCell className="text-center font-bold tabular-nums">
                                            {sum(g.students, 'present')}
                                        </TableCell>
                                        <TableCell className="text-center font-bold tabular-nums">
                                            {sum(g.students, 'paid')}
                                        </TableCell>
                                        <TableCell className="text-center font-bold tabular-nums">
                                            {sum(g.students, 'unpaid')}
                                        </TableCell>
                                        <TableCell className="text-right font-bold">
                                            <Money
                                                value={sum(
                                                    g.students,
                                                    'charged',
                                                )}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right font-bold">
                                            <Money
                                                value={-sum(g.students, 'debt')}
                                                signed
                                            />
                                        </TableCell>
                                        <TableCell className="text-right font-bold">
                                            <Money
                                                value={sum(
                                                    g.students,
                                                    'share_received',
                                                )}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right font-bold">
                                            <Money
                                                value={
                                                    -sum(
                                                        g.students,
                                                        'share_waiting',
                                                    )
                                                }
                                                signed
                                            />
                                        </TableCell>
                                    </TableRow>
                                </tfoot>
                            )}
                        </Table>
                    </div>
                </div>
            ))}
        </section>
    );
}

function sum(students: ReportStudent[], key: keyof ReportStudent): number {
    return students.reduce((total, s) => total + Number(s[key] ?? 0), 0);
}
