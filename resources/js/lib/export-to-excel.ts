import * as XLSX from 'xlsx';

export function toFilenameSlug(value: string): string {
    return (
        value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'export'
    );
}

export function exportToExcel(rows: Record<string, string | number>[], filename: string): void {
    const worksheet = XLSX.utils.json_to_sheet(rows);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Data');
    XLSX.writeFile(workbook, `${toFilenameSlug(filename)}.xlsx`);
}
