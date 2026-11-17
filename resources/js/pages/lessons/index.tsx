import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    DoorOpen,
} from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate, today } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import attendance from '@/routes/attendance';
import lessons from '@/routes/lessons';

type Lesson = {
    id: number;
    group: string;
    start_time: string;
    end_time: string;
    teacher: string | null;
    room: string | null;
    status: string;
    is_substitute: boolean;
    is_makeup: boolean;
    marked: number;
};

export default function LessonsIndex({
    lessons: list,
    date,
}: {
    lessons: Lesson[];
    date: string;
}) {
    const t = useT();
    const go = (value: string) =>
        router.get(
            attendance.index().url,
            { date: value },
            { preserveState: true },
        );
    const shift = (days: number) => {
        const next = new Date(`${date}T00:00:00`);
        next.setDate(next.getDate() + days);
        go(
            `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-${String(next.getDate()).padStart(2, '0')}`,
        );
    };

    return (
        <>
            <Head title={t('Lessons & attendance')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('Lessons & attendance')}
                    description={
                        date === today()
                            ? t('Today, :date', { date: formatDate(date) })
                            : formatDate(date)
                    }
                />
                <div className="flex items-center gap-2">
                    <Button
                        size="icon"
                        variant="outline"
                        onClick={() => shift(-1)}
                    >
                        <ChevronLeft />
                    </Button>
                    <Input
                        type="date"
                        value={date}
                        onChange={(e) => go(e.target.value)}
                        className="w-44"
                    />
                    <Button
                        size="icon"
                        variant="outline"
                        onClick={() => shift(1)}
                    >
                        <ChevronRight />
                    </Button>
                    {date !== today() && (
                        <Button variant="ghost" onClick={() => go(today())}>
                            {t('Today')}
                        </Button>
                    )}
                </div>
                {list.length === 0 ? (
                    <EmptyState>{t('No lessons on this day.')}</EmptyState>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {list.map((lesson) => (
                            <Link
                                key={lesson.id}
                                href={lessons.show(lesson.id)}
                                className={cn(
                                    'rounded-2xl border bg-card p-4 transition-colors hover:border-primary',
                                    lesson.status === 'cancelled' &&
                                        'opacity-60',
                                )}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="text-lg font-extrabold">
                                        {lesson.group}
                                    </div>
                                    {lesson.status === 'held' ? (
                                        <span className="flex items-center gap-1 text-sm font-semibold text-success">
                                            <CheckCircle2 className="size-4" />
                                            {lesson.marked}
                                        </span>
                                    ) : (
                                        <StatusBadge status={lesson.status} />
                                    )}
                                </div>
                                <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                    <span className="flex items-center gap-1">
                                        <Clock className="size-3.5" />
                                        {lesson.start_time}–{lesson.end_time}
                                    </span>
                                    {lesson.room && (
                                        <span className="flex items-center gap-1">
                                            <DoorOpen className="size-3.5" />
                                            {lesson.room}
                                        </span>
                                    )}
                                </div>
                                <div className="mt-2 text-sm">
                                    {lesson.teacher}
                                    {lesson.is_substitute && (
                                        <span className="ml-1 text-xs font-semibold text-highlight">
                                            ({t('substitute')})
                                        </span>
                                    )}
                                    {lesson.is_makeup && (
                                        <span className="ml-1 text-xs font-semibold text-primary">
                                            ({t('extra lesson')})
                                        </span>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

LessonsIndex.layout = {
    breadcrumbs: [{ title: 'Lessons & attendance', href: attendance.index() }],
};
