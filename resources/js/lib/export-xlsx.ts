import exportRoutes from '@/routes/export';

type Cell = string | number | null | undefined;

/**
 * Download the given table as an Excel file (the server writes the .xlsx).
 */
export async function exportXlsx(
    filename: string,
    headers: string[],
    rows: Cell[][],
): Promise<void> {
    const xsrf = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.slice('XSRF-TOKEN='.length) ?? '',
    );
    const response = await fetch(exportRoutes.xlsx().url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/octet-stream',
            'X-XSRF-TOKEN': xsrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            filename,
            headers,
            rows: rows.map((row) => row.map((cell) => cell ?? '')),
        }),
    });

    if (!response.ok) {
        throw new Error(`Export failed (${response.status})`);
    }

    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement('a');
    link.href = url;
    link.download = `${filename}.xlsx`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
