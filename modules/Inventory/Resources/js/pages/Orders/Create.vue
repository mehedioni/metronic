<script setup lang="ts">
import type { Paginated } from '@/types';
import Index from './Index.vue';

interface OrderStatus {
    id: number;
    key: string;
    label: string;
    variant: 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'outline' | 'solid';
}

interface OrderRow {
    id: number;
    order_number: string;
    customer_name: string;
    customer_email: string | null;
    customer_phone: string | null;
    status: OrderStatus;
    total: string;
    items_count: number;
    created_at: string;
    customer: { id: number; code: string; name: string } | null;
    created_by: { id: number; name: string } | null;
}

/**
 * Taking an order opens the order list with its create panel already showing,
 * so the route is linkable without duplicating the list.
 */
const props = defineProps<{
    orders: Paginated<OrderRow>;
    filters: Record<string, unknown>;
    counts?: Record<string, number>;
    listStatuses?: OrderStatus[];
    options: Record<string, any>;
}>();
</script>

<template>
    <Index
        :orders="props.orders"
        :filters="props.filters"
        :counts="props.counts"
        :list-statuses="props.listStatuses"
        :options="props.options"
        :open-create-modal="true"
    />
</template>
