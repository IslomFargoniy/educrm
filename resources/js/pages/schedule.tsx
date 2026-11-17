import { Head, Link } from '@inertiajs/react';
import PageHeader from '@/components/page-header';
import { weekdayName } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { schedule } from '@/routes';
import groups from '@/routes/groups';

type Group = {
    id: number;
    name: string;
    days: number[];
    start_time: string;
    end_time: string;
    room_id: number | null;
    room: string | null;
    teacher_id: number | null;
    teacher: string | null;
};

export default function Schedule({
    rooms,
    groups: list,
}: {
    rooms: { id: number; name: string }[];
    groups: Group[];
}) {
    const t = useT();
    const locale = useLocale();
    const columns = [
        ...rooms.map((r) => ({ id: r.id as number | null, name: r.name })),
        ...(list.some((g) => g.room_id === null)
            ? [{ id: null, name: t('No room') }]
            : []),
    ];
    const overlaps = (a: Group, b: Group) =>
        a.id !== b.id && a.start_time < b.end_time && b.start_time < a.end_time;
    const conflict = (g: Group, day: number) =>
        list.some(
            (o) =>
                o.days.includes(day) &&
                overlaps(g, o) &&
                ((g.room_id !== null && o.room_id === g.room_id) ||
                    (g.teacher_id !== null && o.teacher_id === g.teacher_id)),
        );

    return (
        <>
            <Head title={t('Schedule')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('Schedule')}
                    description={t(
                        'Weekly timetable by rooms. Red — the room or teacher is busy at the same time.',
                    )}
                />
                <div className="overflow-x-auto rounded-xl border bg-card">
                    <table className="w-full min-w-[800px] text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="w-16 px-2 py-2 text-left text-xs text-muted-foreground uppercase">
                                    {t('Day')}
                                </th>
                                {columns.map((c) => (
                                    <th
                                        key={String(c.id)}
                                        className="px-2 py-2 text-left text-xs text-muted-foreground uppercase"
                                    >
                                        {c.name}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {[1, 2, 3, 4, 5, 6, 7].map((day) => (
                                <tr
                                    key={day}
                                    className="border-b align-top last:border-0"
                                >
                                    <td className="px-2 py-2 font-bold">
                                        {weekdayName(day, locale)}
                                    </td>
                                    {columns.map((c) => (
                                        <td
                                            key={String(c.id)}
                                            className="space-y-1 px-2 py-2"
                                        >
                                            {list
                                                .filter(
                                                    (g) =>
                                                        g.days.includes(day) &&
                                                        g.room_id === c.id,
                                                )
                                                .map((g) => (
                                                    <Link
                                                        key={g.id}
                                                        href={groups.show(g.id)}
                                                        className={cn(
                                                            'block rounded-lg border px-2 py-1 hover:border-primary',
                                                            conflict(g, day)
                                                                ? 'border-danger bg-danger/10'
                                                                : 'bg-secondary/60',
                                                        )}
                                                    >
                                                        <div className="text-xs font-bold">
                                                            {g.start_time}–
                                                            {g.end_time}
                                                        </div>
                                                        <div className="font-semibold">
                                                            {g.name}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {g.teacher ?? '—'}
                                                        </div>
                                                    </Link>
                                                ))}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

Schedule.layout = {
    breadcrumbs: [{ title: 'Schedule', href: schedule() }],
};
