import { Form, Head, router } from '@inertiajs/react';
import RowNumber from '@/components/row-number';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';
import admin from '@/routes/admin';

export default function ExpenseCategories({
    categories,
}: {
    categories: { id: number; name: string; is_active: boolean }[];
}) {
    const t = useT();

    return (
        <>
            <Head title={t('Expense categories')} />
            <div className="flex max-w-2xl flex-col gap-5 p-4 md:p-6">
                <PageHeader title={t('Expense categories')} />
                <Form
                    {...admin.expenseCategories.store.form()}
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    className="flex gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <Input
                                name="name"
                                required
                                placeholder={t('e.g. Rent')}
                                aria-invalid={Boolean(errors.name)}
                            />
                            <Button disabled={processing}>{t('Add')}</Button>
                        </>
                    )}
                </Form>
                <ul className="divide-y rounded-xl border bg-card">
                    {categories.map((c, index) => (
                        <li
                            key={c.id}
                            className={`flex items-center justify-between px-4 py-2.5 text-sm ${c.is_active ? '' : 'opacity-50'}`}
                        >
                            <span>
                                <RowNumber value={index + 1} className="mr-3" />
                                {c.name}
                            </span>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() =>
                                    router.put(
                                        admin.expenseCategories.update(c.id)
                                            .url,
                                        {
                                            name: c.name,
                                            is_active: !c.is_active,
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {c.is_active ? t('Disable') : t('Enable')}
                            </Button>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

ExpenseCategories.layout = {
    breadcrumbs: [
        { title: 'Expense categories', href: admin.expenseCategories.index() },
    ],
};
