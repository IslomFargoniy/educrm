import { Head, router, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import RowNumber from '@/components/row-number';
import Field from '@/components/field';
import FormDialog from '@/components/form-dialog';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDate } from '@/lib/format';
import { useT } from '@/lib/i18n';
import admin from '@/routes/admin';

export default function Holidays({
    holidays,
    canGlobal,
}: {
    holidays: {
        id: number;
        date: string;
        name: string;
        branch: string | null;
    }[];
    canGlobal: boolean;
}) {
    const t = useT();
    const { branches, currentBranchId } = usePage().props;

    return (
        <>
            <Head title={t('Holidays')} />
            <div className="flex max-w-3xl flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={t('Holidays')}
                    description={t(
                        'Lessons on these days are cancelled automatically and not billed.',
                    )}
                    actions={
                        <FormDialog
                            title={t('New holiday')}
                            form={admin.holidays.store.form()}
                            trigger={
                                <Button>
                                    <Plus />
                                    {t('Add')}
                                </Button>
                            }
                        >
                            {(errors) => (
                                <>
                                    <Field
                                        label={t('Date')}
                                        error={errors.date}
                                    >
                                        <Input
                                            name="date"
                                            type="date"
                                            required
                                        />
                                    </Field>
                                    <Field
                                        label={t('Name')}
                                        error={errors.name}
                                    >
                                        <Input
                                            name="name"
                                            required
                                            placeholder={t('e.g. Navruz')}
                                        />
                                    </Field>
                                    <Field
                                        label={t('Branch')}
                                        error={errors.branch_id}
                                    >
                                        <NativeSelect
                                            name="branch_id"
                                            defaultValue={
                                                canGlobal
                                                    ? ''
                                                    : (currentBranchId ??
                                                      branches[0]?.id)
                                            }
                                        >
                                            {canGlobal && (
                                                <option value="">
                                                    {t('All branches')}
                                                </option>
                                            )}
                                            {branches.map((b) => (
                                                <option key={b.id} value={b.id}>
                                                    {b.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </Field>
                                </>
                            )}
                        </FormDialog>
                    }
                />
                <ul className="divide-y rounded-xl border bg-card">
                    {holidays.length === 0 && (
                        <li className="p-4 text-sm text-muted-foreground">—</li>
                    )}
                    {holidays.map((h, index) => (
                        <li
                            key={h.id}
                            className="flex items-center justify-between px-4 py-2.5 text-sm"
                        >
                            <span>
                                <RowNumber value={index + 1} className="mr-3" />
                                <b>{formatDate(h.date)}</b> · {h.name}{' '}
                                <span className="text-muted-foreground">
                                    · {h.branch ?? t('All branches')}
                                </span>
                            </span>
                            <Button
                                size="icon"
                                variant="ghost"
                                onClick={() =>
                                    confirm(t('Delete?')) &&
                                    router.delete(
                                        admin.holidays.destroy(h.id).url,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Trash2 />
                            </Button>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

Holidays.layout = {
    breadcrumbs: [{ title: 'Holidays', href: admin.holidays.index() }],
};
