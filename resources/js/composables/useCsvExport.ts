export interface CsvColumn<T> {
    label: string;
    value: (row: T) => unknown;
}

/**
 * Downloads the rows currently on screen as CSV.
 *
 * Deliberately client-side and deliberately page-scoped: it exports exactly
 * what the user is looking at. A full-dataset export belongs on the server,
 * where it can stream and be authorized.
 */
export function useCsvExport() {
    function exportRows<T>(
        filename: string,
        rows: T[],
        columns: Array<CsvColumn<T>>,
    ) {
        const header = columns.map((column) => escape(column.label));
        const body = rows.map((row) =>
            columns.map((column) => escape(column.value(row))),
        );

        const csv = [header, ...body]
            .map((line) => line.join(','))
            .join('\r\n');

        download(`${filename}.csv`, csv);
    }

    return { exportRows };
}

/**
 * RFC 4180 quoting. A leading =, +, - or @ is prefixed with a single quote so
 * a spreadsheet reads the cell as text instead of running it as a formula.
 */
function escape(value: unknown): string {
    const text = value === null || value === undefined ? '' : String(value);
    const guarded = /^[=+\-@\t\r]/.test(text) ? `'${text}` : text;

    return `"${guarded.replace(/"/g, '""')}"`;
}

function download(filename: string, contents: string) {
    // The BOM makes Excel read the file as UTF-8.
    const blob = new Blob([`﻿${contents}`], {
        type: 'text/csv;charset=utf-8;',
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = filename;
    link.click();

    URL.revokeObjectURL(url);
}
