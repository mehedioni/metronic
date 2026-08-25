<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { SlidersHorizontalIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import { FormField, Textarea } from '@/components/form';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableToolbar from '@/components/TableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Drawer } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useCsvExport } from '@/composables/useCsvExport';
import { usePageErrors } from '@/composables/usePageErrors';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { number } from '@/lib/format';
import { humanize } from '@/lib/status';
import products from '@/routes/inventory/products';
import stock from '@/routes/inventory/stock';
import type { Paginated } from '@/types';

interface StockRow {
    id: number;
    product_id: number;
    product_variant_id: number | null;
    quantity_on_hand: number;
    quantity_reserved: number;
    product: {
        id: number;
        name: string;
        sku: string | null;
        low_stock_threshold: number;
        category: { id: number; name: string } | null;
    };
    variant: { id: number; sku: string; name: string } | null;
}

const props = defineProps<{
    items: Paginated<StockRow>;
    filters: Record<string, unknown>;
    categories: Array<{ id: number; name: string }>;
    movementTypes: string[];
}>();

const { can } = usePermissions();
const { firstOf } = usePageErrors();
const { exportRows } = useCsvExport();

const { params, loading, toggleSort, sortState, reset } = useTableQuery({
    url: stock.index.url(),
    filters: props.filters,
    only: ['items', 'filters'],
});

const rows = computed(() => props.items.data);
const adjusting = ref<StockRow | null>(null);

const adjustForm = useForm({
    product_id: '',
    product_variant_id: '' as string | null,
    type: 'adjustment_increase',
    quantity: 1,
    reason: '',
});

const columns: Column[] = [
    { key: 'product.name', label: 'Product', width: '280px' },
    { key: 'product.category.name', label: 'Category', width: '150px' },
    {
        key: 'quantity_on_hand',
        label: 'On hand',
        sort: 'quantity_on_hand',
        align: 'center',
        width: '110px',
    },
    {
        key: 'quantity_reserved',
        label: 'Reserved',
        sort: 'quantity_reserved',
        align: 'center',
        width: '110px',
    },
    { key: 'available', label: 'Available', align: 'center', width: '110px' },
    { key: 'level', label: 'Level', width: '130px' },
];

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Inventory' },
    { label: 'All Stock' },
];

function available(row: StockRow): number {
    return row.quantity_on_hand - row.quantity_reserved;
}

function isLow(row: StockRow): boolean {
    return row.quantity_on_hand <= row.product.low_stock_threshold;
}

function openAdjust(row: StockRow) {
    // Form fields carry strings: that is what an <input>/<select> round-trips,
    // and Laravel's "integer" rule accepts a numeric string.
    adjustForm.product_id = String(row.product_id);
    adjustForm.product_variant_id =
        row.product_variant_id === null ? null : String(row.product_variant_id);
    adjustForm.type = 'adjustment_increase';
    adjustForm.quantity = 1;
    adjustForm.reason = '';

    adjusting.value = row;
}

function adjust() {
    adjustForm.post(stock.adjust.url(), {
        preserveScroll: true,
        onSuccess: () => (adjusting.value = null),
    });
}

function exportCurrent() {
    exportRows('stock', rows.value, [
        { label: 'Product', value: (row) => row.product.name },
        { label: 'Variant', value: (row) => row.variant?.name ?? '' },
        { label: 'SKU', value: (row) => row.variant?.sku ?? row.product.sku ?? '' },
        { label: 'Category', value: (row) => row.product.category?.name ?? '' },
        { label: 'On hand', value: (row) => row.quantity_on_hand },
        { label: 'Reserved', value: (row) => row.quantity_reserved },
        { label: 'Available', value: (row) => available(row) },
        { label: 'Threshold', value: (row) => row.product.low_stock_threshold },
    ]);
}
</script>

<template>
    <Head title="All stock" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="All stock"
            :description="`${number(props.items.total)} stockable units. Available is on hand minus what confirmed orders have reserved.`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Button variant="outline" size="dense" as="a" :href="stock.planner.url()">
                    Plan reorders
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle>Current stock</CardTitle>
                </template>
            </CardHeader>

            <TableToolbar
                v-model:search="params.search"
                v-model:per-page="params.per_page"
                search-placeholder="Search product or SKU"
                exportable
                @export="exportCurrent"
                @clear="reset"
            >
                <template #filters>
                    <Select v-model="params.category_id" class="w-44">
                        <option value="">All categories</option>
                        <option
                            v-for="category in props.categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
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
                empty-title="No stock rows"
                empty-description="A unit appears here once it has been received or adjusted."
                @sort="toggleSort"
            >
                <template #cell-product_name="{ row }">
                    <Link
                        :href="products.show.url(row.product_id)"
                        class="font-medium hover:underline"
                        >{{ row.product.name }}</Link
                    >
                    <span class="block font-mono text-[11px] text-muted-foreground">
                        {{ row.variant?.sku ?? row.product.sku ?? '—' }}
                        <template v-if="row.variant"> · {{ row.variant.name }}</template>
                    </span>
                </template>

                <template #cell-product_category_name="{ row }">
                    <span class="text-muted-foreground">
                        {{ row.product.category?.name ?? '—' }}
                    </span>
                </template>

                <template #cell-available="{ row }">
                    <span class="font-medium">{{ available(row) }}</span>
                </template>

                <template #cell-level="{ row }">
                    <Badge
                        :variant="
                            row.quantity_on_hand <= 0
                                ? 'danger'
                                : isLow(row)
                                  ? 'warning'
                                  : 'success'
                        "
                        size="sm"
                    >
                        {{
                            row.quantity_on_hand <= 0
                                ? 'Out of stock'
                                : isLow(row)
                                  ? 'Low'
                                  : 'In stock'
                        }}
                    </Badge>
                </template>

                <template #actions="{ row }">
                    <Button
                        v-if="can('inventory.adjust')"
                        variant="ghost"
                        size="icon-dense"
                        aria-label="Adjust stock"
                        @click="openAdjust(row)"
                    >
                        <SlidersHorizontalIcon />
                    </Button>
                </template>
            </DataTable>

            <Pagination
                :links="props.items.links"
                :from="props.items.from"
                :to="props.items.to"
                :total="props.items.total"
            />
        </Card>

        <Drawer
            :open="Boolean(adjusting)"
            title="Adjust stock"
            :description="
                adjusting
                    ? `${adjusting.product.name}${adjusting.variant ? ` · ${adjusting.variant.name}` : ''} — currently ${adjusting.quantity_on_hand} on hand`
                    : undefined
            "
            size="sm"
            @update:open="adjusting = null"
        >
            <div class="space-y-4">
                <FormField
                    label="Movement type"
                    :error="adjustForm.errors.type"
                    hint="The type decides the direction; quantity is always positive."
                >
                    <Select v-model="adjustForm.type">
                        <option
                            v-for="type in props.movementTypes"
                            :key="type"
                            :value="type"
                        >
                            {{ humanize(type) }}
                        </option>
                    </Select>
                </FormField>

                <FormField
                    label="Quantity"
                    :error="adjustForm.errors.quantity"
                    required
                >
                    <Input v-model.number="adjustForm.quantity" type="number" min="1" />
                </FormField>

                <FormField label="Reason" :error="adjustForm.errors.reason">
                    <Textarea
                        v-model="adjustForm.reason"
                        :rows="3"
                        placeholder="Recorded on the ledger row"
                    />
                </FormField>

                <p
                    v-if="firstOf('inventory', 'quantity')"
                    class="text-[11px] text-danger"
                >
                    {{ firstOf('inventory', 'quantity') }}
                </p>
            </div>

            <template #footer>
                <Button variant="outline" size="dense" @click="adjusting = null">
                    Cancel
                </Button>
                <Button
                    size="dense"
                    :disabled="adjustForm.processing"
                    @click="adjust"
                >
                    Record adjustment
                </Button>
            </template>
        </Drawer>
    </AppLayout>
</template>
