import { Head, Link, router } from '@inertiajs/react';
import { CalendarPlus, Plus, X } from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/empty-state';
import Field from '@/components/field';
import NameFields from '@/components/name-fields';
import FormDialog from '@/components/form-dialog';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import PhoneInput from '@/components/phone-input';
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
import {
    formatDate,
    formatDateTime,
    formatPhone,
    today,
    weekdayName,
} from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import leads from '@/routes/leads';

type Lead = {
    id: number;
    full_name: string;
    first_name: string | null;
    last_name: string | null;
    phone: string;
    source: string;
    status: string;
    interested_course: string | null;
    course_id: number | null;
    assignee: string | null;
    next_contact_at: string | null;
    created_at: string;
    trial_at: string | null;
    trial_group: string | null;
    teacher: string | null;
};

type TrialGroup = {
    id: number;
    name: string;
    course_id: number | null;
    teacher: string | null;
    days: number[];
    start_time: string;
};

type ListKey = 'orders' | 'trial';

type Props = {
    leads: Paginated<Lead>;
    list: ListKey | null;
    filters: {
        list?: ListKey;
        status?: string;
        source?: string;
        assigned_to?: string;
        q?: string;
        course_id?: string;
        teacher_id?: string;
        from?: string;
        to?: string;
        day?: string;
        parity?: string;
    };
    statusCounts: Record<string, number>;
    staff: { id: number; name: string }[];
    teachers: { id: number; name: string }[];
    courses: { id: number; name: string }[];
    trialGroups: TrialGroup[];
    sources: string[];
    statuses: string[];
};

const LIST_TITLES: Record<ListKey, string> = {
    orders: 'Requests',
    trial: 'Signed up for the first lesson',
};

const LIST_HINTS: Record<ListKey, string> = {
    orders: 'People who came to the reception or called asking for a course.',
    trial: 'The course is known and the date and time of the first lesson are set.',
};

export default function LeadsIndex({
    leads: list,
    list: listKey,
    filters,
    statusCounts,
    staff,
    teachers,
    courses,
    trialGroups,
    sources,
    statuses,
}: Props) {
    const t = useT();
    const locale = useLocale();
    const { can } = useCan();
    const [q, setQ] = useState(filters.q ?? '');
    const [trialFor, setTrialFor] = useState<Lead | null>(null);
    const apply = (next: Partial<Props['filters']>) =>
        router.get(
            leads.index().url,
            Object.fromEntries(
                Object.entries({ ...filters, ...next }).filter(
                    ([, v]) => v !== undefined && v !== '',
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    const trialView = listKey === 'trial';
    const shownStatuses =
        listKey === 'orders'
            ? statuses.filter((s) => s !== 'converted')
            : statuses;
    const anyFilter = [
        'status',
        'source',
        'assigned_to',
        'q',
        'course_id',
        'teacher_id',
        'from',
        'to',
        'day',
        'parity',
    ].some((k) => filters[k as keyof Props['filters']]);

    return (
        <>
            <Head title={listKey ? t(LIST_TITLES[listKey]) : t('Leads')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={listKey ? t(LIST_TITLES[listKey]) : t('Leads')}
                    description={
                        listKey
                            ? t(LIST_HINTS[listKey])
                            : t(
                                  'People who called or wrote. Turn them into students with a trial lesson.',
                              )
                    }
                    actions={
                        can('leads.manage') && (
                            <FormDialog
                                title={t('New lead')}
                                form={leads.store.form()}
                                trigger={
                                    <Button>
                                        <Plus />
                                        {t('Add request')}
                                    </Button>
                                }
                            >
                                {(errors) => (
                                    <LeadFields
                                        errors={errors}
                                        staff={staff}
                                        courses={courses}
                                        sources={sources}
                                    />
                                )}
                            </FormDialog>
                        )
                    }
                />

                <div className="flex flex-wrap items-center gap-1.5">
                    {([null, 'orders', 'trial'] as (ListKey | null)[]).map(
                        (key) => (
                            <button
                                key={key ?? 'all'}
                                type="button"
                                onClick={() =>
                                    router.get(
                                        leads.index().url,
                                        key ? { list: key } : {},
                                        { preserveScroll: true },
                                    )
                                }
                                className={cn(
                                    'rounded-lg border px-3 py-1.5 text-xs font-semibold',
                                    listKey === key
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {key ? t(LIST_TITLES[key]) : t('All leads')}
                            </button>
                        ),
                    )}
                    <span className="mx-1 h-5 w-px bg-border" />
                    {shownStatuses.map((s) => (
                        <button
                            key={s}
                            type="button"
                            onClick={() =>
                                apply({
                                    status:
                                        filters.status === s ? undefined : s,
                                })
                            }
                            className={cn(
                                'rounded-full border px-2.5 py-1 text-xs font-semibold',
                                filters.status === s
                                    ? 'border-primary text-primary'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t(`status.${s}`)}:{' '}
                            <span className="tabular-nums">
                                {statusCounts[s] ?? 0}
                            </span>
                        </button>
                    ))}
                </div>

                <div className="grid gap-2 rounded-xl border bg-card p-3 sm:grid-cols-2 lg:grid-cols-5">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ q: q || undefined });
                        }}
                    >
                        <Input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder={t('Name or phone')}
                        />
                    </form>
                    <Input
                        type="date"
                        value={filters.from ?? ''}
                        onChange={(e) =>
                            apply({ from: e.target.value || undefined })
                        }
                        aria-label={
                            trialView
                                ? t('First lesson from')
                                : t('Created from')
                        }
                        title={
                            trialView
                                ? t('First lesson from')
                                : t('Created from')
                        }
                    />
                    <Input
                        type="date"
                        value={filters.to ?? ''}
                        onChange={(e) =>
                            apply({ to: e.target.value || undefined })
                        }
                        aria-label={
                            trialView ? t('First lesson to') : t('Created to')
                        }
                        title={
                            trialView ? t('First lesson to') : t('Created to')
                        }
                    />
                    <NativeSelect
                        value={filters.course_id ?? ''}
                        onChange={(e) =>
                            apply({ course_id: e.target.value || undefined })
                        }
                        aria-label={t('Course')}
                    >
                        <option value="">{t('All courses')}</option>
                        {courses.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        value={filters.assigned_to ?? ''}
                        onChange={(e) =>
                            apply({ assigned_to: e.target.value || undefined })
                        }
                        aria-label={t('Responsible')}
                    >
                        <option value="">{t('Any moderator')}</option>
                        {staff.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                            </option>
                        ))}
                    </NativeSelect>
                    {!trialView && (
                        <NativeSelect
                            value={filters.source ?? ''}
                            onChange={(e) =>
                                apply({ source: e.target.value || undefined })
                            }
                            aria-label={t('Source')}
                        >
                            <option value="">{t('All sources')}</option>
                            {sources.map((s) => (
                                <option key={s} value={s}>
                                    {t(`source.${s}`)}
                                </option>
                            ))}
                        </NativeSelect>
                    )}
                    {trialView && (
                        <>
                            <NativeSelect
                                value={filters.teacher_id ?? ''}
                                onChange={(e) =>
                                    apply({
                                        teacher_id: e.target.value || undefined,
                                    })
                                }
                                aria-label={t('Teacher')}
                            >
                                <option value="">{t('All teachers')}</option>
                                {teachers.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </NativeSelect>
                            <NativeSelect
                                value={filters.day ?? ''}
                                onChange={(e) =>
                                    apply({ day: e.target.value || undefined })
                                }
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
                                value={filters.parity ?? ''}
                                onChange={(e) =>
                                    apply({
                                        parity: e.target.value || undefined,
                                    })
                                }
                                aria-label={t('Odd / even days')}
                            >
                                <option value="">{t('Odd / even days')}</option>
                                <option value="odd">{t('Odd days')}</option>
                                <option value="even">{t('Even days')}</option>
                            </NativeSelect>
                        </>
                    )}
                    {anyFilter && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="justify-self-start"
                            onClick={() =>
                                router.get(
                                    leads.index().url,
                                    listKey ? { list: listKey } : {},
                                )
                            }
                        >
                            <X />
                            {t('Clear filters')}
                        </Button>
                    )}
                </div>

                <div className="text-xs text-muted-foreground">
                    {t('Total: :count', { count: list.total })}
                </div>

                {list.data.length === 0 ? (
                    <EmptyState>{t('No leads yet.')}</EmptyState>
                ) : (
                    <div className="overflow-x-auto rounded-xl border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12 text-center">
                                        №
                                    </TableHead>
                                    <TableHead className="w-14">ID</TableHead>
                                    <TableHead>{t('Name')}</TableHead>
                                    <TableHead>{t('Phone')}</TableHead>
                                    <TableHead>{t('Created')}</TableHead>
                                    {trialView && (
                                        <TableHead>
                                            {t('First lesson')}
                                        </TableHead>
                                    )}
                                    {trialView && (
                                        <TableHead>{t('Teacher')}</TableHead>
                                    )}
                                    <TableHead>{t('Course')}</TableHead>
                                    {!trialView && (
                                        <TableHead>{t('Source')}</TableHead>
                                    )}
                                    <TableHead>{t('Status')}</TableHead>
                                    {!trialView && (
                                        <TableHead>
                                            {t('Next contact')}
                                        </TableHead>
                                    )}
                                    <TableHead>{t('Moderator')}</TableHead>
                                    {can('leads.manage') && (
                                        <TableHead className="w-10" />
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {list.data.map((lead, index) => {
                                    const missed =
                                        lead.trial_at !== null &&
                                        lead.trial_at.slice(0, 10) < today() &&
                                        lead.status !== 'converted';
                                    const isToday =
                                        lead.trial_at?.slice(0, 10) === today();

                                    return (
                                        <TableRow
                                            key={lead.id}
                                            className={cn(
                                                'cursor-pointer',
                                                trialView &&
                                                    missed &&
                                                    'bg-danger/10',
                                                trialView &&
                                                    isToday &&
                                                    'bg-highlight/10',
                                            )}
                                            onClick={() =>
                                                router.visit(
                                                    leads.show(lead.id).url,
                                                )
                                            }
                                        >
                                            <TableCell className="text-center text-muted-foreground tabular-nums">
                                                {(list.from ?? 1) + index}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground tabular-nums">
                                                {lead.id}
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    href={leads.show(lead.id)}
                                                    className="font-semibold hover:text-primary"
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    {lead.full_name}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                <a
                                                    href={`tel:${lead.phone}`}
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                    className="rounded-full border px-2 py-0.5 text-xs hover:border-primary"
                                                >
                                                    {formatPhone(lead.phone)}
                                                </a>
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground tabular-nums">
                                                {formatDateTime(
                                                    lead.created_at,
                                                )}
                                            </TableCell>
                                            {trialView && (
                                                <TableCell className="text-xs font-semibold tabular-nums">
                                                    {formatDateTime(
                                                        lead.trial_at,
                                                    )}
                                                    {lead.trial_group && (
                                                        <div className="font-normal text-muted-foreground">
                                                            {lead.trial_group}
                                                        </div>
                                                    )}
                                                </TableCell>
                                            )}
                                            {trialView && (
                                                <TableCell>
                                                    {lead.teacher ?? '—'}
                                                </TableCell>
                                            )}
                                            <TableCell>
                                                {lead.interested_course ?? '—'}
                                            </TableCell>
                                            {!trialView && (
                                                <TableCell>
                                                    {t(`source.${lead.source}`)}
                                                </TableCell>
                                            )}
                                            <TableCell>
                                                <StatusBadge
                                                    status={lead.status}
                                                />
                                            </TableCell>
                                            {!trialView && (
                                                <TableCell
                                                    className={cn(
                                                        lead.next_contact_at &&
                                                            lead.next_contact_at <=
                                                                today() &&
                                                            ![
                                                                'converted',
                                                                'rejected',
                                                            ].includes(
                                                                lead.status,
                                                            ) &&
                                                            'font-bold text-danger',
                                                    )}
                                                >
                                                    {formatDate(
                                                        lead.next_contact_at,
                                                    )}
                                                </TableCell>
                                            )}
                                            <TableCell>
                                                {lead.assignee ?? '—'}
                                            </TableCell>
                                            {can('leads.manage') && (
                                                <TableCell>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-8"
                                                        title={t(
                                                            'Sign up for the first lesson',
                                                        )}
                                                        aria-label={t(
                                                            'Sign up for the first lesson',
                                                        )}
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            setTrialFor(lead);
                                                        }}
                                                    >
                                                        <CalendarPlus />
                                                    </Button>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination links={list.links} />
            </div>
            {trialFor && (
                <TrialDialog
                    lead={trialFor}
                    groups={trialGroups}
                    onClose={() => setTrialFor(null)}
                />
            )}
        </>
    );
}

/**
 * Sign a lead up for the first lesson: group (of the lead's course first) and the date and time.
 */
function TrialDialog({
    lead,
    groups,
    onClose,
}: {
    lead: Lead;
    groups: TrialGroup[];
    onClose: () => void;
}) {
    const t = useT();
    const locale = useLocale();
    const sorted = [...groups].sort(
        (a, b) =>
            Number(b.course_id === lead.course_id) -
            Number(a.course_id === lead.course_id),
    );
    const [groupId, setGroupId] = useState<string>(
        lead.trial_at ? '' : String(sorted[0]?.id ?? ''),
    );
    const [date, setDate] = useState(lead.trial_at?.slice(0, 10) ?? today());
    const group = groups.find((g) => String(g.id) === groupId);
    const [time, setTime] = useState(
        lead.trial_at?.slice(11, 16) ?? group?.start_time ?? '14:00',
    );
    const [saving, setSaving] = useState(false);
    const submit = (clear = false) =>
        router.put(
            leads.trial(lead.id).url,
            clear
                ? { trial_group_id: null, trial_at: null }
                : { trial_group_id: groupId, trial_at: `${date} ${time}` },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: onClose,
            },
        );

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {t('Sign up for the first lesson')}
                    </DialogTitle>
                    <DialogDescription>
                        {lead.full_name} ·{' '}
                        {lead.interested_course ?? t('no course chosen')}
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-3">
                    <Field label={t('Group')}>
                        <NativeSelect
                            value={groupId}
                            onChange={(e) => {
                                setGroupId(e.target.value);
                                const next = groups.find(
                                    (g) => String(g.id) === e.target.value,
                                );
                                if (next) {
                                    setTime(next.start_time);
                                }
                            }}
                        >
                            <option value="">—</option>
                            {sorted.map((g) => (
                                <option key={g.id} value={g.id}>
                                    {g.name} ·{' '}
                                    {g.days
                                        .map((d) => weekdayName(d, locale))
                                        .join('-')}{' '}
                                    {g.start_time}
                                    {g.teacher ? ` · ${g.teacher}` : ''}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <div className="grid grid-cols-2 gap-2">
                        <Field label={t('Date')}>
                            <Input
                                type="date"
                                value={date}
                                onChange={(e) => setDate(e.target.value)}
                            />
                        </Field>
                        <Field label={t('Time')}>
                            <Input
                                type="time"
                                value={time}
                                onChange={(e) => setTime(e.target.value)}
                            />
                        </Field>
                    </div>
                </div>
                <DialogFooter>
                    {lead.trial_at && (
                        <Button
                            variant="ghost"
                            className="mr-auto text-danger"
                            disabled={saving}
                            onClick={() => submit(true)}
                        >
                            {t('Cancel the first lesson')}
                        </Button>
                    )}
                    <Button
                        disabled={saving || !groupId || !date || !time}
                        onClick={() => submit()}
                    >
                        {t('Save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function LeadFields({
    errors,
    staff,
    courses,
    sources,
    values,
}: {
    errors: Record<string, string>;
    staff: Props['staff'];
    courses: Props['courses'];
    sources: string[];
    values?: Partial<
        Lead & {
            assigned_to: number | null;
            course_id: number | null;
            note: string | null;
        }
    >;
}) {
    const t = useT();

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <NameFields
                errors={errors}
                firstName={values?.first_name}
                lastName={values?.last_name}
                lastNameRequired={false}
            />
            <Field label={t('Phone')} error={errors.phone}>
                <PhoneInput
                    name="phone"
                    defaultValue={values?.phone}
                    required
                />
            </Field>
            <Field label={t('Source')} error={errors.source}>
                <NativeSelect
                    name="source"
                    defaultValue={values?.source ?? 'instagram'}
                >
                    {sources.map((s) => (
                        <option key={s} value={s}>
                            {t(`source.${s}`)}
                        </option>
                    ))}
                </NativeSelect>
            </Field>
            <Field label={t('Course')} error={errors.course_id}>
                <NativeSelect
                    name="course_id"
                    defaultValue={values?.course_id ?? ''}
                >
                    <option value="">—</option>
                    {courses.map((course) => (
                        <option key={course.id} value={course.id}>
                            {course.name}
                        </option>
                    ))}
                </NativeSelect>
            </Field>
            <Field label={t('Responsible')} error={errors.assigned_to}>
                <NativeSelect
                    name="assigned_to"
                    defaultValue={values?.assigned_to ?? ''}
                >
                    <option value="">{t('Me')}</option>
                    {staff.map((s) => (
                        <option key={s.id} value={s.id}>
                            {s.name}
                        </option>
                    ))}
                </NativeSelect>
            </Field>
            <Field label={t('Next contact')} error={errors.next_contact_at}>
                <Input
                    name="next_contact_at"
                    type="date"
                    defaultValue={values?.next_contact_at ?? ''}
                />
            </Field>
            {values?.status && (
                <input type="hidden" name="status" value={values.status} />
            )}
            <Field
                label={t('Note')}
                error={errors.note}
                className="sm:col-span-2"
            >
                <Input name="note" defaultValue={values?.note ?? ''} />
            </Field>
        </div>
    );
}

LeadsIndex.layout = {
    breadcrumbs: [{ title: 'Leads', href: leads.index() }],
};
