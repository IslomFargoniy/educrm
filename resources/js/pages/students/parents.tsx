import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatPhone } from '@/lib/format';
import { useT } from '@/lib/i18n';
import students from '@/routes/students';

type Parent = {
    id: number;
    name: string;
    phone: string | null;
    telegram: boolean;
    children: { id: number; full_name: string; relation: string | null }[];
};

export default function Parents({
    parents,
    filters,
}: {
    parents: Paginated<Parent>;
    filters: { q: string };
}) {
    const t = useT();
    const [q, setQ] = useState(filters.q ?? '');

    return (
        <>
            <Head title={t('Parents')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('Parents')}
                    description={t(
                        ':count parents · Telegram shows who gets the notifications',
                        {
                            count: parents.total,
                        },
                    )}
                />
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(students.parents().url, q ? { q } : {}, {
                            preserveState: true,
                            replace: true,
                        });
                    }}
                    className="relative w-full sm:w-80"
                >
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder={t('Parent, child or phone')}
                        className="pl-8"
                    />
                </form>
                {parents.data.length === 0 ? (
                    <EmptyState>{t('No parents found.')}</EmptyState>
                ) : (
                    <div className="overflow-x-auto rounded-xl border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12 text-center">
                                        №
                                    </TableHead>
                                    <TableHead>{t('Parent')}</TableHead>
                                    <TableHead>{t('Phone')}</TableHead>
                                    <TableHead>Telegram</TableHead>
                                    <TableHead>{t('Children')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {parents.data.map((p, index) => (
                                    <TableRow key={p.id}>
                                        <TableCell className="text-center text-muted-foreground tabular-nums">
                                            {(parents.from ?? 1) + index}
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            {p.name}
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {p.phone ? (
                                                <a
                                                    href={`tel:${p.phone}`}
                                                    className="hover:text-primary"
                                                >
                                                    {formatPhone(p.phone)}
                                                </a>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {p.telegram ? (
                                                <span className="text-xs font-semibold text-success">
                                                    ✓ {t('Linked')}
                                                </span>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    {t('Not linked')}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {p.children.map((c, i) => (
                                                <span key={c.id}>
                                                    {i > 0 && ', '}
                                                    <Link
                                                        href={students.show(
                                                            c.id,
                                                        )}
                                                        className="font-medium hover:text-primary"
                                                    >
                                                        {c.full_name}
                                                    </Link>
                                                </span>
                                            ))}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination links={parents.links} />
            </div>
        </>
    );
}

Parents.layout = {
    breadcrumbs: [{ title: 'Parents', href: students.parents() }],
};
