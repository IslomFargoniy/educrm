import { Head, Link, router } from '@inertiajs/react';
import {
    Clock,
    DoorOpen,
    LayoutGrid,
    List,
    Plus,
    Users,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import RowNumber from '@/components/row-number';
import EmptyState from '@/components/empty-state';
import FormDialog from '@/components/form-dialog';
import GroupFormFields from '@/components/group-form-fields';
import type { GroupFormOptions } from '@/components/group-form-fields';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
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
import { useCan } from '@/hooks/use-can';
import { formatDate, formatDays, weekdayName } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import groups from '@/routes/groups';

type Group = {
    id: number;
    name: string;
    subject: string | null;
    days: number[];
    start_time: string;
    end_time: string;
    teacher: string | null;
    room: string | null;
    branch: string;
    students_count: number;
    frozen_count: number;
    teacher_percent: number;
    course_id: number | null;
    teacher_id: number | null;
    room_id: number | null;
    start_date: string | null;
    end_date: string | null;
    price: { monthly_price: number; lessons_per_month: number } | null;
};

export default function GroupsIndex({
    groups: list,
    status,
    ...options
}: { groups: Group[]; status: string } & GroupFormOptions) {
    const t = useT();
    const locale = useLocale();
    const { can } = useCan();
    const [view, setView] = useState<'table' | 'cards'>(() => {
        try {
            return (
                (localStorage.getItem('groups.view') as 'table' | 'cards') ??
                'table'
            );
        } catch {
            return 'table';
        }
    });
    const [search, setSearch] = useState('');
    const [teacherId, setTeacherId] = useState('');
    const [courseId, setCourseId] = useState('');
    const [roomId, setRoomId] = useState('');
    const [day, setDay] = useState('');
    const [parity, setParity] = useState<'' | 'odd' | 'even' | 'other'>('');
    const [time, setTime] = useState('');

    const switchView = (next: 'table' | 'cards') => {
        setView(next);

        try {
            localStorage.setItem('groups.view', next);
        } catch {
            // Private mode: the choice just isn't remembered.
        }
    };

    const kind = (days: number[]) => {
        const sorted = [...days].sort().join(',');

        return sorted === '1,3,5'
            ? 'odd'
            : sorted === '2,4,6'
              ? 'even'
              : 'other';
    };

    const filtered = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return list.filter(
            (g) =>
                (needle === '' ||
                    [g.name, g.subject, g.teacher, g.room]
                        .join(' ')
                        .toLowerCase()
                        .includes(needle)) &&
                (teacherId === '' || String(g.teacher_id) === teacherId) &&
                (courseId === '' || String(g.course_id) === courseId) &&
                (roomId === '' || String(g.room_id) === roomId) &&
                (day === '' || g.days.includes(Number(day))) &&
                (parity === '' || kind(g.days) === parity) &&
                (time === '' || (g.start_time <= time && time < g.end_time)),
        );
    }, [list, search, teacherId, courseId, roomId, day, parity, time]);

    const totalStudents = filtered.reduce(
        (sum, g) => sum + g.students_count,
        0,
    );
    const frozenStudents = filtered.reduce((sum, g) => sum + g.frozen_count, 0);
    const anyFilter = Boolean(
        search || teacherId || courseId || roomId || day || parity || time,
    );
    const clear = () => {
        setSearch('');
        setTeacherId('');
        setCourseId('');
        setRoomId('');
        setDay('');
        setParity('');
        setTime('');
    };

    return (
        <>
            <Head title={t('Groups')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('Groups')}
                    description={t(':count groups', { count: filtered.length })}
                    actions={
                        can('groups.manage') && (
                            <FormDialog
                                wide
                                title={t('New group')}
                                form={groups.store.form()}
                                trigger={
                                    <Button>
                                        <Plus />
                                        {t('New group')}
                                    </Button>
                                }
                            >
                                {(errors) => (
                                    <GroupFormFields
                                        errors={errors}
                                        options={options}
                                        creating
                                    />
                                )}
                            </FormDialog>
                        )
                    }
                />
                <div className="flex gap-1">
                    {['active', 'archived'].map((s) => (
                        <Button
                            key={s}
                            size="sm"
                            variant={status === s ? 'default' : 'ghost'}
                            onClick={() =>
                                router.get(
                                    groups.index().url,
                                    { status: s },
                                    { preserveState: true },
                                )
                            }
                        >
                            {t(`status.${s}`)}
                        </Button>
                    ))}
                </div>
                <div className="grid gap-2 rounded-xl border bg-card p-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('Search')}
                    />
                    <NativeSelect
                        value={teacherId}
                        onChange={(e) => setTeacherId(e.target.value)}
                        aria-label={t('Teacher')}
                    >
                        <option value="">{t('All teachers')}</option>
                        {options.teachers.map((o) => (
                            <option key={o.id} value={o.id}>
                                {o.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        value={courseId}
                        onChange={(e) => setCourseId(e.target.value)}
                        aria-label={t('Course')}
                    >
                        <option value="">{t('All courses')}</option>
                        {options.courses.map((o) => (
                            <option key={o.id} value={o.id}>
                                {o.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        value={roomId}
                        onChange={(e) => setRoomId(e.target.value)}
                        aria-label={t('Room')}
                    >
                        <option value="">{t('All rooms')}</option>
                        {options.rooms.map((o) => (
                            <option key={o.id} value={o.id}>
                                {o.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        value={day}
                        onChange={(e) => setDay(e.target.value)}
                        aria-label={t('Day')}
                    >
                        <option value="">{t('Any day')}</option>
                        {[1, 2, 3, 4, 5, 6, 7].map((d) => (
                            <option key={d} value={d}>
                                {weekdayName(d, locale)}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        value={parity}
                        onChange={(e) =>
                            setParity(e.target.value as typeof parity)
                        }
                        aria-label={t('Odd / even days')}
                    >
                        <option value="">{t('Odd / even days')}</option>
                        <option value="odd">{t('Odd days')}</option>
                        <option value="even">{t('Even days')}</option>
                        <option value="other">{t('Other days')}</option>
                    </NativeSelect>
                    <Input
                        type="time"
                        value={time}
                        onChange={(e) => setTime(e.target.value)}
                        aria-label={t('Lesson time')}
                        title={t('Groups that have a lesson at this time')}
                    />
                    <div className="flex items-center justify-end gap-1">
                        {anyFilter && (
                            <Button size="sm" variant="ghost" onClick={clear}>
                                <X />
                                {t('Clear filters')}
                            </Button>
                        )}
                        <Button
                            size="icon"
                            variant={view === 'table' ? 'default' : 'ghost'}
                            className="size-8"
                            aria-label={t('Table')}
                            onClick={() => switchView('table')}
                        >
                            <List />
                        </Button>
                        <Button
                            size="icon"
                            variant={view === 'cards' ? 'default' : 'ghost'}
                            className="size-8"
                            aria-label={t('Cards')}
                            onClick={() => switchView('cards')}
                        >
                            <LayoutGrid />
                        </Button>
                    </div>
                </div>

                <p className="text-sm text-muted-foreground">
                    {t('Students in total')}:{' '}
                    <b className="text-foreground">{totalStudents}</b>
                    {' · '}
                    {t('Studying (not frozen)')}:{' '}
                    <b className="text-foreground">
                        {totalStudents - frozenStudents}
                    </b>
                    {' · '}
                    {t('Frozen')}:{' '}
                    <b className="text-foreground">{frozenStudents}</b>
                </p>

                {filtered.length === 0 ? (
                    <EmptyState>
                        {list.length === 0
                            ? t('No groups yet.')
                            : t('No groups match the filters.')}
                    </EmptyState>
                ) : view === 'table' ? (
                    <div className="overflow-x-auto rounded-xl border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10 text-center">
                                        №
                                    </TableHead>
                                    <TableHead>{t('Group')}</TableHead>
                                    <TableHead>{t('Course')}</TableHead>
                                    <TableHead>{t('Days')}</TableHead>
                                    <TableHead>{t('Lesson time')}</TableHead>
                                    <TableHead>{t('Group period')}</TableHead>
                                    <TableHead className="text-center">
                                        {t('Students')}
                                    </TableHead>
                                    <TableHead>{t('Teacher')}</TableHead>
                                    <TableHead>{t('Room')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filtered.map((g, index) => (
                                    <TableRow
                                        key={g.id}
                                        className={cn(
                                            'cursor-pointer',
                                            kind(g.days) === 'even' &&
                                                'bg-highlight/10',
                                        )}
                                        onClick={() =>
                                            router.visit(groups.show(g.id))
                                        }
                                    >
                                        <TableCell className="text-center text-muted-foreground tabular-nums">
                                            {index + 1}
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            <Link
                                                href={groups.show(g.id)}
                                                className="hover:text-primary"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                {g.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {g.subject ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {kind(g.days) === 'odd'
                                                ? t('Odd days')
                                                : kind(g.days) === 'even'
                                                  ? t('Even days')
                                                  : formatDays(g.days, locale)}
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {g.start_time}–{g.end_time}
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground tabular-nums">
                                            {formatDate(g.start_date)} –{' '}
                                            {g.end_date
                                                ? formatDate(g.end_date)
                                                : '…'}
                                        </TableCell>
                                        <TableCell className="text-center tabular-nums">
                                            <b>{g.students_count}</b>
                                            {g.frozen_count > 0 && (
                                                <span
                                                    className="ml-1 text-xs text-muted-foreground"
                                                    title={t('Frozen')}
                                                >
                                                    ({g.frozen_count} ❄)
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {g.teacher ?? '—'}
                                        </TableCell>
                                        <TableCell>{g.room ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {filtered.map((g, index) => (
                            <Link
                                key={g.id}
                                href={groups.show(g.id)}
                                className={cn(
                                    'group rounded-2xl border bg-card p-4 transition-colors hover:border-primary',
                                )}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <div className="text-lg font-extrabold group-hover:text-primary">
                                            <RowNumber
                                                value={index + 1}
                                                className="mr-2 w-auto text-sm"
                                            />
                                            {g.name}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {g.subject ?? ''}{' '}
                                            {g.teacher && `· ${g.teacher}`}
                                        </div>
                                    </div>
                                    <span className="flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs font-bold text-secondary-foreground">
                                        <Users className="size-3" />
                                        {g.students_count}
                                    </span>
                                </div>
                                <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                    <span className="flex items-center gap-1">
                                        <Clock className="size-3.5" />
                                        {formatDays(g.days, locale)} ·{' '}
                                        {g.start_time}–{g.end_time}
                                    </span>
                                    {g.room && (
                                        <span className="flex items-center gap-1">
                                            <DoorOpen className="size-3.5" />
                                            {g.room}
                                        </span>
                                    )}
                                </div>
                                {g.price && (
                                    <div className="mt-3 flex items-baseline justify-between border-t pt-3 text-sm">
                                        <span>
                                            <Money
                                                value={g.price.monthly_price}
                                                className="font-bold"
                                            />{' '}
                                            / {t('month')}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {t(':n lessons', {
                                                n: g.price.lessons_per_month,
                                            })}{' '}
                                            · {g.teacher_percent}%
                                        </span>
                                    </div>
                                )}
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

GroupsIndex.layout = {
    breadcrumbs: [{ title: 'Groups', href: groups.index() }],
};
