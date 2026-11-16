import { Form, Head, router } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useT } from '@/lib/i18n';
import admin from '@/routes/admin';

type Row = { line: number; full_name: string; phone: string | null; parent_name: string | null; parent_phone: string | null; group: string; joined_at: string | null; balance: number; errors: string[] };

export default function Import({ preview, branchId }: { preview: { branch_id: number; rows: Row[] } | null; branchId: number | null }) {
    const t = useT();
    const valid = preview?.rows.filter((r) => r.errors.length === 0).length ?? 0;

    return (
        <>
            <Head title={t('Excel import')} />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={t('Excel import')}
                    description={t('Students, parents, groups and starting balances from the old system. Balance: positive — paid in advance, negative — debt.')}
                    actions={<Button asChild variant="outline"><a href={admin.import.template().url}><Download />{t('Download template')}</a></Button>}
                />
                {branchId === null && <div className="rounded-xl border border-highlight bg-highlight/10 p-3 text-sm">{t('Choose a branch at the top first.')}</div>}
                {!preview && branchId !== null && (
                    <Form {...admin.import.preview.form()} className="flex flex-wrap items-center gap-2 rounded-xl border bg-card p-4">
                        {({ processing, errors }) => (
                            <>
                                <Input type="file" name="file" accept=".xlsx" required className="max-w-sm" />
                                <Button disabled={processing}><Upload />{t('Check file')}</Button>
                                {errors.file && <span className="text-sm text-danger">{errors.file}</span>}
                            </>
                        )}
                    </Form>
                )}
                {preview && (
                    <>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-sm">{t(':valid of :total rows are correct. Rows with errors are skipped.', { valid, total: preview.rows.length })}</span>
                            <Button disabled={valid === 0} onClick={() => router.post(admin.import.confirm().url)}>{t('Import :n rows', { n: valid })}</Button>
                            <Button variant="ghost" onClick={() => router.post(admin.import.cancel().url)}>{t('Cancel')}</Button>
                        </div>
                        <div className="overflow-hidden rounded-xl border bg-card">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>#</TableHead>
                                        <TableHead>{t('Student')}</TableHead>
                                        <TableHead>{t('Parent')}</TableHead>
                                        <TableHead>{t('Group')}</TableHead>
                                        <TableHead className="text-right">{t('Balance')}</TableHead>
                                        <TableHead>{t('Errors')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {preview.rows.map((r) => (
                                        <TableRow key={r.line} className={r.errors.length ? 'bg-danger/5' : ''}>
                                            <TableCell>{r.line}</TableCell>
                                            <TableCell>{r.full_name}<div className="text-xs text-muted-foreground">{r.phone}</div></TableCell>
                                            <TableCell>{r.parent_name}<div className="text-xs text-muted-foreground">{r.parent_phone}</div></TableCell>
                                            <TableCell>{r.group}</TableCell>
                                            <TableCell className="text-right"><Money value={r.balance} signed /></TableCell>
                                            <TableCell className="text-sm text-danger">{r.errors.join(' ')}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Import.layout = {
    breadcrumbs: [{ title: 'Excel import', href: admin.import.index() }],
};
