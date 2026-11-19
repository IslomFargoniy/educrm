import { PieChart } from '@/components/report-chart';
import { formatMonth } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

export type TeacherKpiData = {
    retention: { joined: number; stayed: number; paid: number; left: number };
    outcomes: {
        studying: number;
        graduated: number;
        dropped: number;
        expelled: number;
        transferred: number;
    };
    lessons: { held: number; not_held: number; cancelled: number };
    attendance: { present: number; absent: number; excused: number };
};

const percent = (part: number, whole: number) =>
    whole > 0 ? Math.round((part / whole) * 100) : 0;

/**
 * Teacher effectiveness for the month: new students kept, graduated / left, lessons held, attendance.
 */
export default function TeacherKpi({
    kpi,
    month,
}: {
    kpi: TeacherKpiData;
    month: string;
}) {
    const t = useT();
    const locale = useLocale();
    const { retention, outcomes, lessons, attendance } = kpi;
    const marked = attendance.present + attendance.absent + attendance.excused;
    const past = lessons.held + lessons.not_held;

    return (
        <section className="space-y-3">
            <h2 className="text-lg font-bold">
                {t('Teacher effectiveness')} ·{' '}
                <span className="capitalize">{formatMonth(month, locale)}</span>
            </h2>
            <div className="grid gap-3 lg:grid-cols-2">
                <Card
                    title={t('New students kept')}
                    hint={t(
                        'Joined the teacher’s groups this month (transfers not counted) and still study.',
                    )}
                    headline={`${percent(retention.stayed, retention.joined)}%`}
                    facts={[
                        [t('Joined'), retention.joined],
                        [t('Still studying'), retention.stayed],
                        [t('Have paid'), retention.paid],
                        [t('Left'), retention.left],
                    ]}
                    items={[
                        { label: t('Still studying'), value: retention.stayed },
                        { label: t('Left'), value: retention.left },
                    ]}
                />
                <Card
                    title={t('Graduated and left')}
                    hint={t(
                        'Who left the teacher’s groups this month and why; studying is the number now.',
                    )}
                    headline={String(outcomes.graduated)}
                    headlineLabel={t('graduated')}
                    facts={[
                        [t('Studying now'), outcomes.studying],
                        [t('leave.graduated'), outcomes.graduated],
                        [t('leave.dropped'), outcomes.dropped],
                        [t('leave.expelled'), outcomes.expelled],
                        [t('Moved to another group'), outcomes.transferred],
                    ]}
                    items={[
                        {
                            label: t('Studying now'),
                            value: outcomes.studying,
                        },
                        {
                            label: t('leave.graduated'),
                            value: outcomes.graduated,
                        },
                        { label: t('leave.dropped'), value: outcomes.dropped },
                        {
                            label: t('leave.expelled'),
                            value: outcomes.expelled,
                        },
                        {
                            label: t('Moved to another group'),
                            value: outcomes.transferred,
                        },
                    ]}
                />
                <Card
                    title={t('Lessons held')}
                    hint={t('Lessons of the month up to today.')}
                    headline={`${percent(lessons.held, past)}%`}
                    facts={[
                        [t('Held'), lessons.held],
                        [t('Not marked'), lessons.not_held],
                        [t('Cancelled'), lessons.cancelled],
                    ]}
                    items={[
                        { label: t('Held'), value: lessons.held },
                        { label: t('Not marked'), value: lessons.not_held },
                        { label: t('Cancelled'), value: lessons.cancelled },
                    ]}
                />
                <Card
                    title={t('Student attendance')}
                    hint={t('Marks in the teacher’s lessons this month.')}
                    headline={`${percent(attendance.present, marked)}%`}
                    facts={[
                        [t('Came'), attendance.present],
                        [t('Absent'), attendance.absent],
                        [t('Excused'), attendance.excused],
                    ]}
                    items={[
                        { label: t('Came'), value: attendance.present },
                        { label: t('Absent'), value: attendance.absent },
                        { label: t('Excused'), value: attendance.excused },
                    ]}
                />
            </div>
        </section>
    );
}

function Card({
    title,
    hint,
    headline,
    headlineLabel,
    facts,
    items,
}: {
    title: string;
    hint: string;
    headline: string;
    headlineLabel?: string;
    facts: [string, number][];
    items: { label: string; value: number }[];
}) {
    return (
        <div className="space-y-3 rounded-xl border bg-card p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="font-bold">{title}</h3>
                    <p className="text-xs text-muted-foreground">{hint}</p>
                </div>
                <div className="text-right">
                    <div className="text-2xl font-extrabold tabular-nums">
                        {headline}
                    </div>
                    {headlineLabel && (
                        <div className="text-xs text-muted-foreground">
                            {headlineLabel}
                        </div>
                    )}
                </div>
            </div>
            <dl className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                {facts.map(([label, value]) => (
                    <div key={label} className="flex gap-1">
                        <dt className="text-muted-foreground">{label}:</dt>
                        <dd className="font-semibold tabular-nums">{value}</dd>
                    </div>
                ))}
            </dl>
            <PieChart items={items} money={false} />
        </div>
    );
}
