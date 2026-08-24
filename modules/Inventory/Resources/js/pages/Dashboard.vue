<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import DataCard from '@/components/DataCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface Statistics {
    totals: Record<string, number>;
    orders_by_status: Record<string, number>;
    movement_summary: {
        inbound_units: number;
        outbound_units: number;
        by_type: Record<string, number>;
    };
    low_stock_items: Array<{
        id: string;
        quantity_on_hand: number;
        product: { name: string; sku: string | null };
        variant: { sku: string } | null;
    }>;
    recent_movements: Array<{
        id: string;
        type: string;
        quantity: number;
        created_at: string;
        product: { name: string };
    }>;
    recent_orders: Array<{
        id: string;
        order_number: string;
        status: string;
        total: string;
    }>;
    recent_receipts: Array<{
        id: string;
        reference_number: string;
        status: string;
        items_sum_quantity: number | null;
    }>;
}

defineProps<{ statistics?: Statistics }>();
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout title="Dashboard">
        <Deferred data="statistics">
            <template #fallback>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="n in 8"
                        :key="n"
                        class="h-24 animate-pulse rounded-lg border border-border bg-muted/40"
                    />
                </div>
            </template>

            <div v-if="statistics" class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="(value, key) in statistics.totals"
                        :key="key"
                        class="rounded-lg border border-border bg-card p-4"
                    >
                        <p class="text-sm text-muted-foreground capitalize">
                            {{ String(key).replace(/_/g, ' ') }}
                        </p>
                        <p class="text-2xl font-semibold">{{ value }}</p>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <DataCard title="Low stock">
                        <table class="w-full text-sm">
                            <tbody>
                                <tr
                                    v-for="item in statistics.low_stock_items"
                                    :key="item.id"
                                    class="border-t border-border"
                                >
                                    <td class="px-4 py-2">
                                        {{ item.product.name }}
                                        <span
                                            v-if="item.variant"
                                            class="text-muted-foreground"
                                            >· {{ item.variant.sku }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ item.quantity_on_hand }}
                                    </td>
                                </tr>
                                <tr v-if="!statistics.low_stock_items.length">
                                    <td
                                        class="px-4 py-3 text-muted-foreground"
                                        colspan="2"
                                    >
                                        Nothing below threshold.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </DataCard>

                    <DataCard title="Stock movement summary">
                        <div class="space-y-2 p-4 text-sm">
                            <p>
                                Inbound units:
                                <strong>{{
                                    statistics.movement_summary.inbound_units
                                }}</strong>
                            </p>
                            <p>
                                Outbound units:
                                <strong>{{
                                    statistics.movement_summary.outbound_units
                                }}</strong>
                            </p>
                            <div
                                v-for="(count, status) in statistics.orders_by_status"
                                :key="status"
                                class="flex justify-between border-t border-border pt-2"
                            >
                                <span class="capitalize">{{ status }} orders</span>
                                <span>{{ count }}</span>
                            </div>
                        </div>
                    </DataCard>

                    <DataCard title="Recent movements">
                        <table class="w-full text-sm">
                            <tbody>
                                <tr
                                    v-for="movement in statistics.recent_movements"
                                    :key="movement.id"
                                    class="border-t border-border"
                                >
                                    <td class="px-4 py-2">
                                        {{ movement.product.name }}
                                    </td>
                                    <td class="px-4 py-2 text-muted-foreground">
                                        {{ movement.type }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ movement.quantity }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </DataCard>

                    <DataCard title="Recent orders">
                        <table class="w-full text-sm">
                            <tbody>
                                <tr
                                    v-for="order in statistics.recent_orders"
                                    :key="order.id"
                                    class="border-t border-border"
                                >
                                    <td class="px-4 py-2">
                                        {{ order.order_number }}
                                    </td>
                                    <td class="px-4 py-2 capitalize">
                                        {{ order.status }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ order.total }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </DataCard>
                </div>
            </div>
        </Deferred>
    </AppLayout>
</template>
