<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import CreateResourceCard from '@/components/CreateResourceCard.vue';
import DataCard from '@/components/DataCard.vue';
import Pagination from '@/components/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import routes from '@/routes/inventory/products';
import type { Paginated } from '@/types';

const props = defineProps<{
    products: Paginated<Record<string, any>>;
    filters: Record<string, unknown>;
    options: Record<string, any>;
}>();

const { can } = usePermissions();

const filters = reactive({
    search: (props.filters.search as string) ?? '',
    low_stock: Boolean(props.filters.low_stock),
});

watch(filters, (value) => {
    router.get(routes.index.url(), { ...value }, {
        preserveState: true,
        replace: true,
    });
});

const productFields = computed(() => [
    { name: 'name', label: 'Name', required: true },
    { name: 'sku', label: 'SKU' },
    {
        name: 'category_id',
        label: 'Category',
        type: 'select' as const,
        options: (props.options.categories ?? []).map(
            (category: { id: string; name: string }) => ({
                value: category.id,
                label: category.name,
            }),
        ),
    },
    {
        name: 'primary_supplier_id',
        label: 'Primary supplier',
        type: 'select' as const,
        options: (props.options.suppliers ?? []).map(
            (supplier: { id: string; company_name: string }) => ({
                value: supplier.id,
                label: supplier.company_name,
            }),
        ),
    },
    { name: 'selling_price', label: 'Selling price', type: 'number' as const },
    { name: 'low_stock_threshold', label: 'Low stock threshold', type: 'number' as const, value: 0 },
]);

/** Reads a dotted path off a row so the table stays declarative. */
function value(row: Record<string, any>, path: string): unknown {
    return path.split('.').reduce<any>((carry, key) => carry?.[key], row) ?? '—';
}
</script>

<template>
    <Head title="Products" />

    <AppLayout title="Products">
        <div class="space-y-6">
            <CreateResourceCard
                v-if="can('products.create')"
                title="New product"
                action="/inventory/products"
                :fields="productFields"
            />

            <DataCard title="Filters">
                <div class="flex flex-wrap gap-3 p-4">
                    <input
                        v-model="filters.search"
                        placeholder="Search name or SKU"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="filters.low_stock" type="checkbox" />
                        Low stock only
                    </label>
                </div>
            </DataCard>

            <DataCard title="Products">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-4 py-2">Name</th>
                                <th class="px-4 py-2">SKU</th>
                                <th class="px-4 py-2">Category</th>
                                <th class="px-4 py-2">Primary supplier</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Variants</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in props.products.data"
                                :key="row.id"
                                class="border-t border-border"
                            >
                                <td class="px-4 py-2">
                                    <Link
                                        :href="routes.show.url(row.id)"
                                        class="underline"
                                        >{{ value(row, 'name') }}</Link
                                    >
                                </td>
                                <td class="px-4 py-2">{{ value(row, 'sku') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'category.name') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'primary_supplier.company_name') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'status') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'variants_count') }}</td>
                            </tr>
                            <tr v-if="!props.products.data.length">
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
                    :links="props.products.links"
                    :from="props.products.from"
                    :to="props.products.to"
                    :total="props.products.total"
                />
            </DataCard>
        </div>
    </AppLayout>
</template>
