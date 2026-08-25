<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { PencilIcon, PlusIcon, Trash2Icon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TableToolbar from '@/components/TableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Drawer } from '@/components/ui/drawer';
import { Dropdown, DropdownItem } from '@/components/ui/dropdown';
import { Select } from '@/components/ui/select';
import { useCsvExport } from '@/composables/useCsvExport';
import { usePageErrors } from '@/composables/usePageErrors';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { money, number } from '@/lib/format';
import productRoutes from '@/routes/inventory/products';
import type { Paginated } from '@/types';

interface InventoryRow {
    quantity_on_hand: number;
    quantity_reserved: number;
}

interface ProductRow {
    id: number;
    name: string;
    sku: string | null;
    status: string;
    type: string;
    selling_price: string | null;
    cost_price: string | null;
    variants_count: number;
    low_stock_threshold: number;
    category: { id: number; name: string } | null;
    primary_supplier: { id: number; company_name: string } | null;
    inventory_items: InventoryRow[];
}

const props = defineProps<{
    products: Paginated<ProductRow>;
    filters: Record<string, unknown>;
    options: {
        categories?: Array<{ id: number; name: string }>;
        suppliers?: Array<{ id: number; company_name: string }>;
        statuses?: string[];
        types?: string[];
    };
}>();

const { can } = usePermissions();
const { firstOf } = usePageErrors();
const { exportRows } = useCsvExport();

const { params, loading, toggleSort, sortState, reset } = useTableQuery({
    url: productRoutes.index.url(),
    filters: props.filters,
    only: ['products', 'filters'],
});

const rows = computed(() => props.products.data);
const selected = ref<ProductRow[]>([]);
const confirming = ref<ProductRow | null>(null);

const columns: Column[] = [
    { key: 'name', label: 'Product', sort: 'name', width: '280px' },
    { key: 'category.name', label: 'Category', width: '140px' },
    { key: 'selling_price', label: 'Price', sort: 'selling_price', width: '110px' },
    { key: 'stock', label: 'Stock', align: 'center', width: '110px' },
    { key: 'status', label: 'Status', sort: 'status', width: '110px' },
    { key: 'variants_count', label: 'Variants', align: 'center', width: '90px' },
    {
        key: 'primary_supplier.company_name',
        label: 'Supplier',
        width: '160px',
    },
];

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Products' },
    { label: 'Product List' },
];

/** On-hand across every stockable unit of the product. */
function onHand(row: ProductRow): number {
    return (row.inventory_items ?? []).reduce(
        (total, item) => total + item.quantity_on_hand,
        0,
    );
}

function isLow(row: ProductRow): boolean {
    return onHand(row) <= row.low_stock_threshold;
}

function destroy() {
    if (!confirming.value) {
        return;
    }

    router.delete(productRoutes.destroy.url(confirming.value.id), {
        preserveScroll: true,
        onFinish: () => (confirming.value = null),
    });
}

function exportCurrent() {
    const source = selected.value.length ? selected.value : rows.value;

    exportRows('products', source, [
        { label: 'Name', value: (row) => row.name },
        { label: 'SKU', value: (row) => row.sku ?? '' },
        { label: 'Category', value: (row) => row.category?.name ?? '' },
        { label: 'Type', value: (row) => row.type },
        { label: 'Status', value: (row) => row.status },
        { label: 'Cost price', value: (row) => row.cost_price ?? '' },
        { label: 'Selling price', value: (row) => row.selling_price ?? '' },
        { label: 'On hand', value: (row) => onHand(row) },
        { label: 'Variants', value: (row) => row.variants_count },
        {
            label: 'Primary supplier',
            value: (row) => row.primary_supplier?.company_name ?? '',
        },
    ]);
}
</script>

<template>
    <Head title="Products" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="Products"
            :description="`${number(props.products.total)} products in the catalogue`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Button
                    v-if="can('products.create')"
                    size="dense"
                    as="a"
                    :href="productRoutes.create.url()"
                >
                    <PlusIcon />
                    New product
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle>Product list</CardTitle>
                </template>
            </CardHeader>

            <TableToolbar
                v-model:search="params.search"
                v-model:per-page="params.per_page"
                search-placeholder="Search name or SKU"
                exportable
                :selected-count="selected.length"
                @export="exportCurrent"
                @clear="reset"
            >
                <template #filters>
                    <Select v-model="params.category_id" class="w-40">
                        <option value="">All categories</option>
                        <option
                            v-for="category in props.options.categories ?? []"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </Select>

                    <Select v-model="params.status" class="w-36">
                        <option value="">All statuses</option>
                        <option
                            v-for="status in props.options.statuses ?? []"
                            :key="status"
                            :value="status"
                        >
                            {{ status }}
                        </option>
                    </Select>

                    <Select v-model="params.supplier_id" class="w-44">
                        <option value="">All suppliers</option>
                        <option
                            v-for="supplier in props.options.suppliers ?? []"
                            :key="supplier.id"
                            :value="supplier.id"
                        >
                            {{ supplier.company_name }}
                        </option>
                    </Select>

                    <label
                        class="flex h-8.5 items-center gap-2 rounded-md border border-input px-3 text-xs shadow-xs"
                    >
                        <input v-model="params.low_stock" type="checkbox" />
                        Low stock only
                    </label>
                </template>
            </TableToolbar>

            <DataTable
                :columns="columns"
                :rows="rows"
                :loading="loading"
                :sort-state="sortState"
                selectable
                empty-title="No products"
                empty-description="Nothing matches these filters yet."
                @sort="toggleSort"
                @selection-change="selected = $event as ProductRow[]"
            >
                <template #cell-name="{ row }">
                    <Link
                        :href="productRoutes.show.url(row.id)"
                        class="font-medium text-foreground hover:underline"
                        >{{ row.name }}</Link
                    >
                    <span class="block font-mono text-[11px] text-muted-foreground">
                        {{ row.sku ?? 'No SKU' }}
                    </span>
                </template>

                <template #cell-selling_price="{ row }">
                    {{ money(row.selling_price) }}
                </template>

                <template #cell-stock="{ row }">
                    <Badge :variant="isLow(row) ? 'warning' : 'neutral'" size="sm">
                        {{ number(onHand(row)) }}
                    </Badge>
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge :status="row.status" size="sm" />
                </template>

                <template #cell-variants_count="{ row }">
                    <span class="text-muted-foreground">
                        {{ row.variants_count || '—' }}
                    </span>
                </template>

                <template #cell-primary_supplier_company_name="{ row }">
                    <span class="text-muted-foreground">
                        {{ row.primary_supplier?.company_name ?? '—' }}
                    </span>
                </template>

                <template #actions="{ row }">
                    <Dropdown>
                        <template #trigger>
                            <Button
                                variant="ghost"
                                size="icon-dense"
                                aria-label="Row actions"
                            >
                                <span class="text-base leading-none">⋯</span>
                            </Button>
                        </template>

                        <DropdownItem as-child>
                            <Link
                                :href="productRoutes.show.url(row.id)"
                                class="flex w-full items-center gap-2"
                                >View</Link
                            >
                        </DropdownItem>

                        <DropdownItem v-if="can('products.update')" as-child>
                            <Link
                                :href="productRoutes.edit.url(row.id)"
                                class="flex w-full items-center gap-2"
                            >
                                <PencilIcon />
                                Edit
                            </Link>
                        </DropdownItem>

                        <DropdownItem
                            v-if="can('products.delete')"
                            destructive
                            @select="confirming = row"
                        >
                            <Trash2Icon />
                            Delete
                        </DropdownItem>
                    </Dropdown>
                </template>

                <template v-if="can('products.create')" #empty-action>
                    <Button size="dense" as="a" :href="productRoutes.create.url()">
                        <PlusIcon />
                        New product
                    </Button>
                </template>
            </DataTable>

            <Pagination
                :links="props.products.links"
                :from="props.products.from"
                :to="props.products.to"
                :total="props.products.total"
            />
        </Card>

        <Drawer
            :open="Boolean(confirming)"
            title="Delete product"
            :description="
                confirming
                    ? `${confirming.name} will be removed from the catalogue. A product that already has stock history cannot be deleted.`
                    : undefined
            "
            size="sm"
            @update:open="confirming = null"
        >
            <p class="text-[0.8125rem] text-muted-foreground">
                This cannot be undone from the UI.
            </p>

            <p v-if="firstOf('inventory')" class="mt-3 text-[11px] text-danger">
                {{ firstOf('inventory') }}
            </p>

            <template #footer>
                <Button variant="outline" size="dense" @click="confirming = null">
                    Cancel
                </Button>
                <Button variant="destructive" size="dense" @click="destroy">
                    Delete product
                </Button>
            </template>
        </Drawer>
    </AppLayout>
</template>
