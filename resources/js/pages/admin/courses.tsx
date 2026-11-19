import { Form, Head, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';
import admin from '@/routes/admin';

type Course = {
    id: number;
    name: string;
    is_active: boolean;
    groups_count: number;
};

export default function Courses({ courses }: { courses: Course[] }) {
    const t = useT();

    return (
        <>
            <Head title={t('Courses')} />
            <div className="flex max-w-2xl flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={t('Courses')}
                    description={t(
                        'Groups and leads choose a course from this list.',
                    )}
                />
                <Form
                    {...admin.courses.store.form()}
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-1"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex gap-2">
                                <Input
                                    name="name"
                                    required
                                    placeholder={t('e.g. IELTS')}
                                    aria-invalid={Boolean(errors.name)}
                                />
                                <Button disabled={processing}>
                                    {t('Add')}
                                </Button>
                            </div>
                            {errors.name && (
                                <p className="text-sm text-danger">
                                    {errors.name}
                                </p>
                            )}
                        </>
                    )}
                </Form>
                {courses.length > 0 && (
                    <ul className="divide-y rounded-xl border bg-card">
                        {courses.map((course, index) => (
                            <li
                                key={course.id}
                                className={`flex items-center gap-3 px-4 py-2 text-sm ${course.is_active ? '' : 'opacity-50'}`}
                            >
                                <span className="w-6 text-right text-xs text-muted-foreground tabular-nums">
                                    {index + 1}
                                </span>
                                <Form
                                    {...admin.courses.update.form(course.id)}
                                    options={{ preserveScroll: true }}
                                    className="flex min-w-0 flex-1 items-center gap-2"
                                >
                                    {({ processing, isDirty }) => (
                                        <>
                                            <Input
                                                name="name"
                                                defaultValue={course.name}
                                                required
                                                className="h-8"
                                            />
                                            <input
                                                type="hidden"
                                                name="is_active"
                                                value={
                                                    course.is_active ? '1' : '0'
                                                }
                                            />
                                            {isDirty && (
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    disabled={processing}
                                                    aria-label={t('Save')}
                                                >
                                                    <Check />
                                                </Button>
                                            )}
                                        </>
                                    )}
                                </Form>
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    {t(':count groups', {
                                        count: course.groups_count,
                                    })}
                                </span>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        router.put(
                                            admin.courses.update(course.id).url,
                                            {
                                                name: course.name,
                                                is_active: !course.is_active,
                                            },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {course.is_active
                                        ? t('Disable')
                                        : t('Enable')}
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

Courses.layout = {
    breadcrumbs: [{ title: 'Courses', href: admin.courses.index() }],
};
