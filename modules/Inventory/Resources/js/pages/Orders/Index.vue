<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import CreateResourceCard from '@/components/CreateResourceCard.vue';
import DataCard from '@/components/DataCard.vue';
import Pagination from '@/components/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import routes from '@/routes/inventory/orders';
import type { Paginated } from '@/types';

const props = defineProps<{
    orders: Paginated<Record<string, any>>;
    filters: Record<string, unknown>;
    options: Record<string, any>;
}>();

const { can } = usePermissions();

const filters = reactive({
    search: (props.filters.search as string) ?? '',
});

watch(filters, (value) => {
    router.get(routes.index.url(), { ...value }, {
        preserveState: true,
        replace: true,
    });
});

const orderFields = computed(() => [
    { name: 'customer_name', label: 'Customer name', required: true },
    { name: 'customer_email', label: 'Customer email', type: 'email' as const },
    {
        name: 'product_id',
        label: 'Product',
        type: 'select' as const,
        options: (props.options.products ?? []).map(
            (product: { id: string; name: string }) => ({
                value: product.id,
                label: product.name,
            }),
        ),
    },
    { name: 'quantity', label: 'Quantity', type: 'number' as const, value: 1 },
    { name: 'unit_price', label: 'Unit price', type: 'number' as const },
]);

/** Reads a dotted path off a row so the table stays declarative. */
function value(row: Record<string, any>, path: string): unknown {
    return path.split('.').reduce<any>((carry, key) => carry?.[key], row) ?? '—';
}
</script>

<template>
    <Head title="Orders" />

    <AppLayout title="Orders">
        <div class="space-y-6">
            <CreateResourceCard
                v-if="can('orders.create')"
                title="New order"
                description="Single-line order entry placeholder; stock is only reserved once the order is confirmed."
                :fields="orderFields"
                :items-from="['product_id', 'quantity', 'unit_price']"
                action="/inventory/orders"
            />

            <DataCard title="Filters">
                <div class="flex flex-wrap gap-3 p-4">
                    <input
                        v-model="filters.search"
                        placeholder="Search order number, customer"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                </div>
            </DataCard>

            <DataCard title="Orders">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-4 py-2">Order</th>
                                <th class="px-4 py-2">Customer</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Lines</th>
                                <th class="px-4 py-2">Total</th>
                                <th class="px-4 py-2">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in props.orders.data"
                                :key="row.id"
                                class="border-t border-border"
                            >
                                <td class="px-4 py-2">
                                    <Link
                                        :href="routes.show.url(row.id)"
                                        class="underline"
                                        >{{ value(row, 'order_number') }}</Link
                                    >
                                </td>
                                <td class="px-4 py-2">{{ value(row, 'customer_name') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'status') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'items_count') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'total') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'created_at') }}</td>
                            </tr>
                            <tr v-if="!props.orders.data.length">
                                <td
                                    class="px-4 py-3 text-muted-foreground"
                                    colspan="6"
                                >
                                    Nothing to show yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    :links="props.orders.links"
                    :from="props.orders.from"
                    :to="props.orders.to"
                    :total="props.orders.total"
                />
            </DataCard>
        </div>
    </AppLayout>
</template>
