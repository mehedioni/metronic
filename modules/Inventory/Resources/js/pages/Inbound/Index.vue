<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import CreateResourceCard from '@/components/CreateResourceCard.vue';
import DataCard from '@/components/DataCard.vue';
import Pagination from '@/components/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import routes from '@/routes/inventory/inbound';
import type { Paginated } from '@/types';

const props = defineProps<{
    receipts: Paginated<Record<string, any>>;
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

const receiptFields = computed(() => [
    {
        name: 'source',
        label: 'Source',
        type: 'select' as const,
        options: (props.options.sources ?? []).map((source: string) => ({
            value: source,
            label: source,
        })),
        value: 'supplier',
    },
    {
        name: 'supplier_id',
        label: 'Supplier',
        type: 'select' as const,
        options: (props.options.suppliers ?? []).map(
            (supplier: { id: string; company_name: string }) => ({
                value: supplier.id,
                label: supplier.company_name,
            }),
        ),
    },
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
    { name: 'unit_cost', label: 'Unit cost', type: 'number' as const },
]);

/** Reads a dotted path off a row so the table stays declarative. */
function value(row: Record<string, any>, path: string): unknown {
    return path.split('.').reduce<any>((carry, key) => carry?.[key], row) ?? '—';
}
</script>

<template>
    <Head title="Receiving" />

    <AppLayout title="Receiving">
        <div class="space-y-6">
            <CreateResourceCard
                v-if="can('inventory.create')"
                title="New receipt"
                description="One line per receipt here; the full multi-line entry screen comes with the final UI."
                :fields="receiptFields"
                :items-from="['product_id', 'quantity', 'unit_cost']"
                action="/inventory/inbound"
            />

            <DataCard title="Filters">
                <div class="flex flex-wrap gap-3 p-4">
                    <input
                        v-model="filters.search"
                        placeholder="Search reference or supplier"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                </div>
            </DataCard>

            <DataCard title="Receiving">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-4 py-2">Reference</th>
                                <th class="px-4 py-2">Supplier</th>
                                <th class="px-4 py-2">Source</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Units</th>
                                <th class="px-4 py-2">Received</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in props.receipts.data"
                                :key="row.id"
                                class="border-t border-border"
                            >
                                <td class="px-4 py-2">
                                    <Link
                                        :href="routes.show.url(row.id)"
                                        class="underline"
                                        >{{ value(row, 'reference_number') }}</Link
                                    >
                                </td>
                                <td class="px-4 py-2">{{ value(row, 'supplier.company_name') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'source') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'status') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'items_sum_quantity') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'received_date') }}</td>
                            </tr>
                            <tr v-if="!props.receipts.data.length">
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
                    :links="props.receipts.links"
                    :from="props.receipts.from"
                    :to="props.receipts.to"
                    :total="props.receipts.total"
                />
            </DataCard>
        </div>
    </AppLayout>
</template>
