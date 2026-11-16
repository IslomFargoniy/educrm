import { Head, Link } from '@inertiajs/react';
import EmptyState from '@/components/empty-state';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useT } from '@/lib/i18n';
import teachers from '@/routes/teachers';

type Teacher = { id: number; name: string; phone: string | null; balance: number; pending: number; groups: string[]; is_active: boolean };

export default function TeachersIndex({ teachers: list }: { teachers: Teacher[] }) {
    const t = useT();

    return (
        <>
            <Head title={t('Teachers')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader title={t('Teachers')} description={t('Balance grows only when students pay for lessons.')} />
                {list.length === 0 ? (
                    <EmptyState>{t('No teachers yet. Add them in Users.')}</EmptyState>
                ) : (
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Teacher')}</TableHead>
                                    <TableHead>{t('Groups')}</TableHead>
                                    <TableHead className="text-right">{t('Balance')}</TableHead>
                                    <TableHead className="text-right">{t('Waiting for payment')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {list.map((teacher) => (
                                    <TableRow key={teacher.id} className={teacher.is_active ? '' : 'opacity-50'}>
                                        <TableCell>
                                            <Link href={teachers.show(teacher.id)} className="font-semibold hover:text-primary">{teacher.name}</Link>
                                            <div className="text-xs text-muted-foreground">{teacher.phone}</div>
                                        </TableCell>
                                        <TableCell className="text-sm">{teacher.groups.join(', ') || '—'}</TableCell>
                                        <TableCell className="text-right font-bold"><Money value={teacher.balance} /></TableCell>
                                        <TableCell className="text-right text-muted-foreground"><Money value={teacher.pending} /></TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

TeachersIndex.layout = {
    breadcrumbs: [{ title: 'Teachers', href: teachers.index() }],
};
