<script setup lang="ts">
import type { Paginated } from '@/types';
import Index from './Index.vue';

interface OrderRow {
    id: number;
    order_number: string;
    customer_name: string;
    customer_email: string | null;
    status: string;
    total: string;
    currency: string;
    items_count: number;
    created_at: string;
    customer: { id: number; code: string; name: string } | null;
    created_by: { id: number; name: string } | null;
}

const props = defineProps<{
    orders: Paginated<OrderRow>;
    filters: Record<string, unknown>;
    counts?: {
        all: number;
        in_transit: number;
        delivered: number;
        returns: number;
        canceled: number;
    };
    options: Record<string, any>;
}>();
</script>

<template>
    <Index
        :orders="props.orders"
        :filters="props.filters"
        :counts="props.counts"
        :options="props.options"
        :open-create-modal="true"
    />
</template>
