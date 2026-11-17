import { Head, Link, router } from '@inertiajs/react';
import { Ban, CheckCheck, RotateCcw, UserRoundCog } from 'lucide-react';
import { useState } from 'react';
import Field from '@/components/field';
import FormDialog from '@/components/form-dialog';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatDateTime } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import attendanceRoutes from '@/routes/attendance';
import groups from '@/routes/groups';
import lessons from '@/routes/lessons';

type Status = 'present' | 'absent' | 'excused';

type Props = {
    lesson: {
        id: number;
        date: string;
        start_time: string;
        end_time: string;
        status: string;
        cancel_reason: string | null;
        is_substitute: boolean;
        is_makeup: boolean;
        teacher_id: number | null;
        teacher: string | null;
        room: string | null;
        group: { id: number; name: string; teacher_id: number | null };
    };
    roster: {
        enrollment_id: number;
        student_id: number;
        full_name: string;
        phone: string | null;
        status: Status | null;
        enrollment_status: string;
        trial_left: number | null;
        is_debtor: boolean | null;
        balance: number | null;
        history:
            | {
                  from: string | null;
                  to: string;
                  by: string | null;
                  at: string;
              }[]
            | null;
    }[];
    canMark: boolean;
    canManage: boolean;
    teachers: { id: number; name: string }[];
};

const options: { value: Status; label: string; className: string }[] = [
    {
        value: 'present',
        label: 'Present',
        className: 'data-[on=true]:bg-success data-[on=true]:text-white',
    },
    {
        value: 'absent',
        label: 'Absent',
        className: 'data-[on=true]:bg-danger data-[on=true]:text-white',
    },
    {
        value: 'excused',
        label: 'Excused',
        className: 'data-[on=true]:bg-sky-600 data-[on=true]:text-white',
    },
];

export default function LessonShow({
    lesson,
    roster,
    canMark,
    canManage,
    teachers,
}: Props) {
    const t = useT();
    const [statuses, setStatuses] = useState<Record<number, Status | null>>(
        Object.fromEntries(roster.map((r) => [r.enrollment_id, r.status])),
    );
    const [saving, setSaving] = useState(false);
    const changed = roster.some((r) => statuses[r.enrollment_id] !== r.status);
    const unmarked = roster.filter((r) => !statuses[r.enrollment_id]).length;

    const save = () => {
        const payload = Object.fromEntries(
            Object.entries(statuses).filter(([, value]) => value),
        );
        setSaving(true);
        router.post(
            lessons.attendance(lesson.id).url,
            { statuses: payload },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <>
            <Head title={`${lesson.group.name} · ${formatDate(lesson.date)}`} />
            <div className="flex flex-col gap-4 p-4 pb-28 md:p-6">
                <PageHeader
                    title={
                        <Link
                            href={groups.show(lesson.group.id)}
                            className="hover:text-primary"
                        >
                            {lesson.group.name}
                        </Link>
                    }
                    description={
                        <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span>
                                {formatDate(lesson.date)} · {lesson.start_time}–
                                {lesson.end_time}
                            </span>
                            {lesson.room && <span>{lesson.room}</span>}
                            <span>
                                {lesson.teacher}
                                {lesson.is_substitute && (
                                    <span className="ml-1 font-semibold text-highlight">
                                        ({t('substitute')})
                                    </span>
                                )}
                            </span>
                            <StatusBadge status={lesson.status} />
                        </span>
                    }
                    actions={
                        canManage && (
                            <>
                                {lesson.status !== 'cancelled' && (
                                    <FormDialog
                                        title={t('Change teacher')}
                                        description={t(
                                            'The share of this lesson goes to the teacher who teaches it.',
                                        )}
                                        form={lessons.update.form(lesson.id)}
                                        trigger={
                                            <Button variant="outline">
                                                <UserRoundCog />
                                                {t('Substitute')}
                                            </Button>
                                        }
                                    >
                                        {(errors) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="teacher"
                                                />
                                                <Field
                                                    label={t('Teacher')}
                                                    error={errors.teacher_id}
                                                >
                                                    <NativeSelect
                                                        name="teacher_id"
                                                        defaultValue={
                                                            lesson.teacher_id ??
                                                            ''
                                                        }
                                                    >
                                                        {teachers.map(
                                                            (teacher) => (
                                                                <option
                                                                    key={
                                                                        teacher.id
                                                                    }
                                                                    value={
                                                                        teacher.id
                                                                    }
                                                                >
                                                                    {
                                                                        teacher.name
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </NativeSelect>
                                                </Field>
                                            </>
                                        )}
                                    </FormDialog>
                                )}
                                {lesson.status !== 'cancelled' ? (
                                    <FormDialog
                                        destructive
                                        title={t('Cancel lesson')}
                                        description={t(
                                            'Students are not billed for a cancelled lesson. You can add an extra lesson in the group page.',
                                        )}
                                        form={lessons.update.form(lesson.id)}
                                        submitLabel={t('Cancel lesson')}
                                        trigger={
                                            <Button
                                                variant="outline"
                                                className="text-danger"
                                            >
                                                <Ban />
                                                {t('Cancel lesson')}
                                            </Button>
                                        }
                                    >
                                        {(errors) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="cancel"
                                                />
                                                <Field
                                                    label={t('Reason')}
                                                    error={errors.cancel_reason}
                                                >
                                                    <Input
                                                        name="cancel_reason"
                                                        placeholder={t(
                                                            'Teacher is ill',
                                                        )}
                                                    />
                                                </Field>
                                            </>
                                        )}
                                    </FormDialog>
                                ) : (
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            router.patch(
                                                lessons.update(lesson.id).url,
                                                { action: 'restore' },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <RotateCcw />
                                        {t('Restore lesson')}
                                    </Button>
                                )}
                            </>
                        )
                    }
                />

                {lesson.status === 'cancelled' && (
                    <div className="rounded-xl border bg-muted p-4 text-sm">
                        {t('Lesson cancelled')}
                        {lesson.cancel_reason && `: ${lesson.cancel_reason}`}
                    </div>
                )}

                {!canMark && lesson.status !== 'cancelled' && (
                    <div className="rounded-xl border border-highlight/50 bg-highlight/10 p-3 text-sm">
                        {t(
                            'Attendance can be marked only on the lesson day. Later changes are made by the administrator.',
                        )}
                    </div>
                )}

                {lesson.status !== 'cancelled' && (
                    <div className="space-y-2">
                        {canMark && (
                            <div className="flex justify-end">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setStatuses(
                                            Object.fromEntries(
                                                roster.map((r) => [
                                                    r.enrollment_id,
                                                    statuses[r.enrollment_id] ??
                                                        'present',
                                                ]),
                                            ),
                                        )
                                    }
                                >
                                    <CheckCheck />
                                    {t('Everyone else present')}
                                </Button>
                            </div>
                        )}
                        {roster.map((r) => (
                            <div
                                key={r.enrollment_id}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card p-3"
                            >
                                <div className="min-w-0">
                                    <div className="font-semibold">
                                        {r.full_name}
                                        {r.enrollment_status === 'trial' && (
                                            <span className="ml-2 rounded-full bg-highlight/20 px-2 py-0.5 text-xs font-semibold">
                                                {t('trial')}
                                                {r.trial_left !== null &&
                                                    ` · ${t(':n left', { n: r.trial_left })}`}
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                        {r.phone && <span>{r.phone}</span>}
                                        {r.balance !== null ? (
                                            <span>
                                                {t('Balance')}:{' '}
                                                <Money
                                                    value={r.balance}
                                                    signed
                                                />
                                            </span>
                                        ) : (
                                            r.is_debtor && (
                                                <span className="font-semibold text-danger">
                                                    {t('debtor')}
                                                </span>
                                            )
                                        )}
                                        {r.history && r.history.length > 1 && (
                                            <span
                                                title={r.history
                                                    .map(
                                                        (h) =>
                                                            `${formatDateTime(h.at)} ${h.by ?? ''}: ${h.from ?? '—'} → ${h.to}`,
                                                    )
                                                    .join('\n')}
                                            >
                                                {t('changed :n times', {
                                                    n: r.history.length - 1,
                                                })}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div className="flex gap-1">
                                    {options.map((option) => (
                                        <button
                                            key={option.value}
                                            type="button"
                                            disabled={!canMark}
                                            data-on={
                                                statuses[r.enrollment_id] ===
                                                option.value
                                            }
                                            onClick={() =>
                                                setStatuses({
                                                    ...statuses,
                                                    [r.enrollment_id]:
                                                        option.value,
                                                })
                                            }
                                            className={cn(
                                                'rounded-lg border px-3 py-2 text-sm font-semibold transition-colors disabled:cursor-not-allowed',
                                                option.className,
                                                statuses[r.enrollment_id] !==
                                                    option.value &&
                                                    'bg-background hover:bg-muted',
                                            )}
                                        >
                                            {t(option.label)}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ))}
                        {roster.length === 0 && (
                            <div className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                {t('No students in this lesson.')}
                            </div>
                        )}
                    </div>
                )}
            </div>

            {canMark && lesson.status !== 'cancelled' && roster.length > 0 && (
                <div className="fixed inset-x-0 bottom-0 z-20 border-t bg-background/95 p-3 backdrop-blur md:left-auto md:w-[calc(100%-var(--sidebar-width))]">
                    <div className="mx-auto flex max-w-3xl items-center justify-between gap-3">
                        <span className="text-sm text-muted-foreground">
                            {unmarked > 0
                                ? t(':n not marked', { n: unmarked })
                                : t('All marked')}
                        </span>
                        <Button
                            size="lg"
                            disabled={!changed || saving}
                            onClick={save}
                            className="min-w-40"
                        >
                            {saving && <Spinner />}
                            {t('Save attendance')}
                        </Button>
                    </div>
                </div>
            )}
        </>
    );
}

LessonShow.layout = {
    breadcrumbs: [
        { title: 'Lessons & attendance', href: attendanceRoutes.index() },
    ],
};
