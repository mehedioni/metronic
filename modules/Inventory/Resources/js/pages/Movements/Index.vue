<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import DataCard from '@/components/DataCard.vue';
import Pagination from '@/components/Pagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import routes from '@/routes/inventory/movements';
import type { Paginated } from '@/types';

const props = defineProps<{
    movements: Paginated<Record<string, any>>;
    filters: Record<string, unknown>;
    types: string[];
}>();

const filters = reactive({
    type: (props.filters.type as string) ?? '',
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
    <Head title="Stock movements" />

    <AppLayout title="Stock movements">
        <div class="space-y-6">
            <DataCard title="Filters">
                <div class="flex flex-wrap gap-3 p-4">
                    <select
                        v-model="filters.type"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    >
                        <option value="">All types</option>
                        <option v-for="option in props.types" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                </div>
            </DataCard>

            <DataCard title="Stock movements">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-4 py-2">Product</th>
                                <th class="px-4 py-2">Variant</th>
                                <th class="px-4 py-2">Type</th>
                                <th class="px-4 py-2">Qty</th>
                                <th class="px-4 py-2">Before</th>
                                <th class="px-4 py-2">After</th>
                                <th class="px-4 py-2">Supplier</th>
                                <th class="px-4 py-2">User</th>
                                <th class="px-4 py-2">When</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in props.movements.data"
                                :key="row.id"
                                class="border-t border-border"
                            >
                                <td class="px-4 py-2">{{ value(row, 'product.name') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'variant.sku') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'type') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'quantity') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'quantity_before') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'quantity_after') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'supplier.company_name') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'user.name') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'created_at') }}</td>
                            </tr>
                            <tr v-if="!props.movements.data.length">
                                <td
                                    class="px-4 py-3 text-muted-foreground"
                                    colspan="9"
                                >
                                    Nothing to show yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    :links="props.movements.links"
                    :from="props.movements.from"
                    :to="props.movements.to"
                    :total="props.movements.total"
                />
            </DataCard>
        </div>
    </AppLayout>
</template>
