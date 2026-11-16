import { Form, Head, Link, router } from '@inertiajs/react';
import { GraduationCap, Pencil } from 'lucide-react';
import Field from '@/components/field';
import FormDialog from '@/components/form-dialog';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { formatDate, formatDateTime, today } from '@/lib/format';
import { useT } from '@/lib/i18n';
import leads from '@/routes/leads';
import leadNotes from '@/routes/leads/notes';
import students from '@/routes/students';
import { LeadFields } from './index';

type Props = {
    lead: {
        id: number;
        full_name: string;
        phone: string;
        source: string;
        status: string;
        interested_course: string | null;
        note: string | null;
        assigned_to: number | null;
        assignee: string | null;
        next_contact_at: string | null;
        created_at: string;
        student_id: number | null;
        student: string | null;
        notes: { id: number; text: string; by: string | null; at: string }[];
    };
    staff: { id: number; name: string }[];
    groups: { id: number; name: string }[];
    sources: string[];
    statuses: string[];
};

export default function LeadShow({ lead, staff, groups, sources, statuses }: Props) {
    const t = useT();
    const { can } = useCan();
    const setStatus = (status: string) => router.put(leads.update(lead.id).url, { ...lead, status }, { preserveScroll: true });

    return (
        <>
            <Head title={lead.full_name} />
            <div className="flex max-w-4xl flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={<span className="flex items-center gap-2">{lead.full_name} <StatusBadge status={lead.status} /></span>}
                    description={[lead.phone, t(`source.${lead.source}`), lead.interested_course, lead.assignee].filter(Boolean).join(' · ')}
                    actions={
                        can('leads.manage') && (
                            <>
                                {!lead.student_id && can('students.create') && (
                                    <FormDialog
                                        title={t('Sign up for a trial')}
                                        description={t('A student is created and added to the group with trial lessons.')}
                                        form={students.store.form()}
                                        trigger={<Button className="bg-highlight text-highlight-foreground hover:bg-highlight/90"><GraduationCap />{t('Sign up for a trial')}</Button>}
                                    >
                                        {(errors) => (
                                            <>
                                                <input type="hidden" name="lead_id" value={lead.id} />
                                                <Field label={t('Full name')} error={errors.full_name}><Input name="full_name" defaultValue={lead.full_name} required /></Field>
                                                <Field label={t('Parent phone')} error={errors.guardian_phone}><Input name="guardian_phone" defaultValue={lead.phone} /></Field>
                                                <Field label={t('Group')} error={errors.group_id}>
                                                    <NativeSelect name="group_id" required>
                                                        {groups.map((g) => <option key={g.id} value={g.id}>{g.name}</option>)}
                                                    </NativeSelect>
                                                </Field>
                                                <div className="grid grid-cols-2 gap-3">
                                                    <Field label={t('From date')} error={errors.joined_at}><Input name="joined_at" type="date" defaultValue={today()} /></Field>
                                                    <Field label={t('Trial lessons')} error={errors.trial_lessons}><Input name="trial_lessons" type="number" min={1} max={30} defaultValue={1} /></Field>
                                                </div>
                                            </>
                                        )}
                                    </FormDialog>
                                )}
                                <FormDialog wide title={t('Edit lead')} form={leads.update.form(lead.id)} trigger={<Button variant="outline"><Pencil />{t('Edit')}</Button>}>
                                    {(errors) => <LeadFields errors={errors} staff={staff} sources={sources} values={lead} />}
                                </FormDialog>
                            </>
                        )
                    }
                />

                {lead.student_id && (
                    <div className="rounded-xl border border-success/40 bg-success/10 p-3 text-sm">
                        {t('Became a student')}: <Link href={students.show(lead.student_id)} className="font-semibold underline">{lead.student}</Link>
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="text-muted-foreground">{t('Status')}:</span>
                    {statuses.map((s) => (
                        <Button key={s} size="sm" variant={lead.status === s ? 'default' : 'outline'} disabled={!can('leads.manage')} onClick={() => setStatus(s)}>
                            {t(`status.${s}`)}
                        </Button>
                    ))}
                    {lead.next_contact_at && <span className="ml-auto">{t('Next contact')}: <b>{formatDate(lead.next_contact_at)}</b></span>}
                </div>

                {lead.note && <p className="rounded-xl border bg-card p-3 text-sm">{lead.note}</p>}

                <section className="space-y-3">
                    <h2 className="text-lg font-bold">{t('Notes and calls')}</h2>
                    {can('leads.manage') && (
                        <Form {...leadNotes.store.form(lead.id)} resetOnSuccess options={{ preserveScroll: true }} className="flex flex-col gap-2">
                            {({ processing }) => (
                                <>
                                    <Textarea name="text" required placeholder={t('What was discussed?')} />
                                    <Button disabled={processing} className="self-end">{t('Add note')}</Button>
                                </>
                            )}
                        </Form>
                    )}
                    <ul className="space-y-2">
                        {lead.notes.map((n) => (
                            <li key={n.id} className="rounded-xl border bg-card p-3 text-sm">
                                <div className="mb-1 text-xs text-muted-foreground">{formatDateTime(n.at)} · {n.by}</div>
                                <p className="whitespace-pre-line">{n.text}</p>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

LeadShow.layout = {
    breadcrumbs: [{ title: 'Leads', href: leads.index() }],
};
