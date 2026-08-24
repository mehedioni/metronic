<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import DataCard from '@/components/DataCard.vue';
import Pagination from '@/components/Pagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import routes from '@/routes/inventory/shipments';
import type { Paginated } from '@/types';

const props = defineProps<{
    shipments: Paginated<Record<string, any>>;
    filters: Record<string, unknown>;
    statuses: string[];
}>();

const filters = reactive({
    search: (props.filters.search as string) ?? '',
    status: (props.filters.status as string) ?? '',
});

watch(filters, (value) => {
    router.get(routes.index.url(), { ...value }, {
        preserveState: true,
        replace: true,
    });
});

/** Reads a dotted path off a row so the table stays declarative. */
function value(row: Record<string, any>, path: string): unknown {
    return path.split('.').reduce<any>((carry, key) => carry?.[key], row) ?? '—';
}
</script>

<template>
    <Head title="Shipments" />

    <AppLayout title="Shipments">
        <div class="space-y-6">
            <DataCard title="Filters">
                <div class="flex flex-wrap gap-3 p-4">
                    <input
                        v-model="filters.search"
                        placeholder="Search shipment, tracking, order"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <select
                        v-model="filters.status"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option v-for="option in props.statuses" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                </div>
            </DataCard>

            <DataCard title="Shipments">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-4 py-2">Shipment</th>
                                <th class="px-4 py-2">Order</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Carrier</th>
                                <th class="px-4 py-2">Tracking</th>
                                <th class="px-4 py-2">Shipped</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in props.shipments.data"
                                :key="row.id"
                                class="border-t border-border"
                            >
                                <td class="px-4 py-2">
                                    <Link
                                        :href="routes.show.url(row.id)"
                                        class="underline"
                                        >{{ value(row, 'shipment_number') }}</Link
                                    >
                                </td>
                                <td class="px-4 py-2">{{ value(row, 'order.order_number') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'status') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'carrier') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'tracking_number') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'shipped_at') }}</td>
                            </tr>
                            <tr v-if="!props.shipments.data.length">
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
                    :links="props.shipments.links"
                    :from="props.shipments.from"
                    :to="props.shipments.to"
                    :total="props.shipments.total"
                />
            </DataCard>
        </div>
    </AppLayout>
</template>
