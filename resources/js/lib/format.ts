/**
 * Display formatting.
 *
 * Money arrives from Laravel as a decimal string ("400.00") so it keeps full
 * precision in transit; these helpers are the only place it becomes a number,
 * and only for display.
 */
export function money(
    value: number | string | null | undefined,
    currency = 'USD',
): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(Number(value));
}

export function number(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return new Intl.NumberFormat().format(Number(value));
}

export function percent(
    value: number | null | undefined,
    fractionDigits = 1,
): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${value > 0 ? '+' : ''}${value.toFixed(fractionDigits)}%`;
}

/** "24 Aug, 2026" — the date format the design uses in tables. */
export function date(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

export function dateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

/** Reads a dotted path off a row, so table columns stay declarative. */
export function get(row: Record<string, any>, path: string): unknown {
    return path.split('.').reduce<any>((carry, key) => carry?.[key], row);
}
