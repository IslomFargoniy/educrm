import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import Field from '@/components/field';
import FormDialog from '@/components/form-dialog';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
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
import { formatDate, today, formatPhone } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import leads from '@/routes/leads';

type Lead = {
    id: number;
    full_name: string;
    phone: string;
    source: string;
    status: string;
    interested_course: string | null;
    assignee: string | null;
    next_contact_at: string | null;
    created_at: string;
};

type Props = {
    leads: Paginated<Lead>;
    filters: {
        status?: string;
        source?: string;
        assigned_to?: string;
        q?: string;
    };
    staff: { id: number; name: string }[];
    sources: string[];
    statuses: string[];
};

export default function LeadsIndex({
    leads: list,
    filters,
    staff,
    sources,
    statuses,
}: Props) {
    const t = useT();
    const { can } = useCan();
    const apply = (next: Partial<Props['filters']>) =>
        router.get(
            leads.index().url,
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title={t('Leads')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('Leads')}
                    description={t(
                        'People who called or wrote. Turn them into students with a trial lesson.',
                    )}
                    actions={
                        can('leads.manage') && (
                            <FormDialog
                                title={t('New lead')}
                                form={leads.store.form()}
                                trigger={
                                    <Button>
                                        <Plus />
                                        {t('Add lead')}
                                    </Button>
                                }
                            >
                                {(errors) => (
                                    <LeadFields
                                        errors={errors}
                                        staff={staff}
                                        sources={sources}
                                    />
                                )}
                            </FormDialog>
                        )
                    }
                />
                <div className="flex flex-wrap gap-2">
                    <NativeSelect
                        className="w-44"
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            apply({ status: e.target.value || undefined })
                        }
                    >
                        <option value="">{t('All statuses')}</option>
                        {statuses.map((s) => (
                            <option key={s} value={s}>
                                {t(`status.${s}`)}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        className="w-44"
                        value={filters.source ?? ''}
                        onChange={(e) =>
                            apply({ source: e.target.value || undefined })
                        }
                    >
                        <option value="">{t('All sources')}</option>
                        {sources.map((s) => (
                            <option key={s} value={s}>
                                {t(`source.${s}`)}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        className="w-44"
                        value={filters.assigned_to ?? ''}
                        onChange={(e) =>
                            apply({ assigned_to: e.target.value || undefined })
                        }
                    >
                        <option value="">{t('Everyone')}</option>
                        {staff.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                            </option>
                        ))}
                    </NativeSelect>
                </div>
                {list.data.length === 0 ? (
                    <EmptyState>{t('No leads yet.')}</EmptyState>
                ) : (
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12 text-center">
                                        №
                                    </TableHead>
                                    <TableHead>{t('Name')}</TableHead>
                                    <TableHead>{t('Course')}</TableHead>
                                    <TableHead>{t('Source')}</TableHead>
                                    <TableHead>{t('Status')}</TableHead>
                                    <TableHead>{t('Next contact')}</TableHead>
                                    <TableHead>{t('Responsible')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {list.data.map((lead, index) => (
                                    <TableRow
                                        key={lead.id}
                                        className="cursor-pointer"
                                        onClick={() =>
                                            router.visit(
                                                leads.show(lead.id).url,
                                            )
                                        }
                                    >
                                        <TableCell className="w-12 text-center text-muted-foreground tabular-nums">
                                            {(list.from ?? 1) + index}
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                href={leads.show(lead.id)}
                                                className="font-semibold hover:text-primary"
                                            >
                                                {lead.full_name}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {formatPhone(lead.phone)}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {lead.interested_course ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {t(`source.${lead.source}`)}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={lead.status} />
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                lead.next_contact_at &&
                                                    lead.next_contact_at <=
                                                        today() &&
                                                    ![
                                                        'converted',
                                                        'rejected',
                                                    ].includes(lead.status) &&
                                                    'font-bold text-danger',
                                            )}
                                        >
                                            {formatDate(lead.next_contact_at)}
                                        </TableCell>
                                        <TableCell>
                                            {lead.assignee ?? '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination links={list.links} />
            </div>
        </>
    );
}

export function LeadFields({
    errors,
    staff,
    sources,
    values,
}: {
    errors: Record<string, string>;
    staff: Props['staff'];
    sources: string[];
    values?: Partial<
        Lead & { assigned_to: number | null; note: string | null }
    >;
}) {
    const t = useT();

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <Field label={t('Full name')} error={errors.full_name}>
                <Input
                    name="full_name"
                    defaultValue={values?.full_name}
                    required
                />
            </Field>
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
            <Field label={t('Course')} error={errors.interested_course}>
                <Input
                    name="interested_course"
                    defaultValue={values?.interested_course ?? ''}
                />
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
