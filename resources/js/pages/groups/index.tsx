import { Head, Link, router } from '@inertiajs/react';
import { Clock, DoorOpen, Plus, Users } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import FormDialog from '@/components/form-dialog';
import GroupFormFields from '@/components/group-form-fields';
import type { GroupFormOptions } from '@/components/group-form-fields';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { formatDays } from '@/lib/format';
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
    teacher_percent: number;
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

    return (
        <>
            <Head title={t('Groups')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('Groups')}
                    description={t(':count groups', { count: list.length })}
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
                {list.length === 0 ? (
                    <EmptyState>{t('No groups yet.')}</EmptyState>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {list.map((g) => (
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
