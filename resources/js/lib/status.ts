import type { BadgeVariants } from '@/components/ui/badge';

type Variant = NonNullable<BadgeVariants['variant']>;

/**
 * Domain status -> badge variant.
 *
 * Kept in one place so a status reads the same colour on every screen, and so
 * a new backend status shows up as a neutral badge rather than an untinted
 * gap. The keys are the enum values the backend sends.
 */
const VARIANTS: Record<string, Variant> = {
    // Shared record status
    active: 'success',
    inactive: 'neutral',

    // Products
    draft: 'neutral',
    archived: 'neutral',

    // Orders
    pending: 'warning',
    confirmed: 'info',
    processing: 'info',
    completed: 'success',
    cancelled: 'danger',

    // Inbound receipts
    received: 'success',
};

export function statusVariant(status: string | null | undefined): Variant {
    if (!status) {
        return 'neutral';
    }

    return VARIANTS[status.toLowerCase()] ?? 'neutral';
}

/** "order_out" -> "Order out" */
export function humanize(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const words = value.replace(/[_-]+/g, ' ').trim().toLowerCase();

    return words.charAt(0).toUpperCase() + words.slice(1);
}
