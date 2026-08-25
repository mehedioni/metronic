<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableToolbar from '@/components/TableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useCsvExport } from '@/composables/useCsvExport';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateTime, number } from '@/lib/format';
import { humanize } from '@/lib/status';
import movementRoutes from '@/routes/inventory/movements';
import products from '@/routes/inventory/products';
import type { Paginated } from '@/types';

interface MovementRow {
    id: number;
    type: string;
    quantity: number;
    quantity_before: number;
    quantity_after: number;
    reason: string | null;
    created_at: string;
    product: { id: number; name: string; sku: string | null } | null;
    variant: { id: number; sku: string } | null;
    supplier: { id: number; company_name: string } | null;
    user: { id: number; name: string } | null;
}

const props = defineProps<{
    movements: Paginated<MovementRow>;
    filters: Record<string, unknown>;
    types: string[];
}>();

const { exportRows } = useCsvExport();

const { params, loading, toggleSort, sortState, reset } = useTableQuery({
    url: movementRoutes.index.url(),
    filters: props.filters,
    only: ['movements', 'filters'],
});

const rows = computed(() => props.movements.data);

/** The heading reflects the direction filter, since the sidebar links to it. */
const heading = computed(() => {
    if (params.direction_flow === 'inbound') {
        return 'Inbound stock';
    }

    if (params.direction_flow === 'outbound') {
        return 'Outbound stock';
    }

    return 'Stock movements';
});

const columns: Column[] = [
    { key: 'created_at', label: 'When', sort: 'created_at', width: '170px' },
    { key: 'product.name', label: 'Product', width: '260px' },
    { key: 'type', label: 'Type', sort: 'type', width: '160px' },
    {
        key: 'quantity',
        label: 'Change',
        sort: 'quantity',
        align: 'center',
        width: '100px',
    },
    { key: 'balance', label: 'Balance', align: 'center', width: '130px' },
    { key: 'reason', label: 'Reason', width: '220px' },
    { key: 'user.name', label: 'By', width: '140px' },
];

const breadcrumbs = computed(() => [
    { label: 'Store Inventory' },
    { label: 'Inventory' },
    { label: heading.value },
]);

function exportCurrent() {
    exportRows('stock-movements', rows.value, [
        { label: 'When', value: (row) => row.created_at },
        { label: 'Product', value: (row) => row.product?.name ?? '' },
        { label: 'SKU', value: (row) => row.variant?.sku ?? row.product?.sku ?? '' },
        { label: 'Type', value: (row) => row.type },
        { label: 'Change', value: (row) => row.quantity },
        { label: 'Before', value: (row) => row.quantity_before },
        { label: 'After', value: (row) => row.quantity_after },
        { label: 'Reason', value: (row) => row.reason ?? '' },
        { label: 'By', value: (row) => row.user?.name ?? '' },
    ]);
}
</script>

<template>
    <Head :title="heading" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            :title="heading"
            :description="`${number(props.movements.total)} ledger entries. The ledger is append-only — a mistake is corrected with a compensating movement, never an edit.`"
            :breadcrumbs="breadcrumbs"
        />

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle>Ledger</CardTitle>
                </template>
            </CardHeader>

            <TableToolbar
                v-model:search="params.search"
                v-model:per-page="params.per_page"
                exportable
                @export="exportCurrent"
                @clear="reset"
            >
                <template #filters>
                    <Select v-model="params.direction_flow" class="w-36">
                        <option value="">In and out</option>
                        <option value="inbound">Inbound only</option>
                        <option value="outbound">Outbound only</option>
                    </Select>

                    <Select v-model="params.type" class="w-48">
                        <option value="">All types</option>
                        <option v-for="type in props.types" :key="type" :value="type">
                            {{ humanize(type) }}
                        </option>
                    </Select>

                    <Input
                        v-model="params.from"
                        type="date"
                        class="w-40"
                        aria-label="From date"
                    />
                    <Input
                        v-model="params.to"
                        type="date"
                        class="w-40"
                        aria-label="To date"
                    />
                </template>
            </TableToolbar>

            <DataTable
                :columns="columns"
                :rows="rows"
                :loading="loading"
                :sort-state="sortState"
                empty-title="No movements"
                empty-description="Nothing matches these filters yet."
                @sort="toggleSort"
            >
                <template #cell-created_at="{ row }">
                    <span class="text-muted-foreground">{{
                        dateTime(row.created_at)
                    }}</span>
                </template>

                <template #cell-product_name="{ row }">
                    <Link
                        v-if="row.product"
                        :href="products.show.url(row.product.id)"
                        class="font-medium hover:underline"
                        >{{ row.product.name }}</Link
                    >
                    <span v-else>—</span>
                    <span class="block font-mono text-[11px] text-muted-foreground">
                        {{ row.variant?.sku ?? row.product?.sku ?? '—' }}
                    </span>
                </template>

                <template #cell-type="{ row }">
                    {{ humanize(row.type) }}
                </template>

                <template #cell-quantity="{ row }">
                    <Badge
                        :variant="row.quantity >= 0 ? 'success' : 'danger'"
                        size="sm"
                    >
                        {{ row.quantity > 0 ? '+' : '' }}{{ row.quantity }}
                    </Badge>
                </template>

                <template #cell-balance="{ row }">
                    <span class="font-mono text-[11px] text-muted-foreground">
                        {{ row.quantity_before }} → {{ row.quantity_after }}
                    </span>
                </template>

                <template #cell-reason="{ row }">
                    <span class="text-muted-foreground">{{ row.reason ?? '—' }}</span>
                </template>

                <template #cell-user_name="{ row }">
                    <span class="text-muted-foreground">{{
                        row.user?.name ?? 'System'
                    }}</span>
                </template>
            </DataTable>

            <Pagination
                :links="props.movements.links"
                :from="props.movements.from"
                :to="props.movements.to"
                :total="props.movements.total"
            />
        </Card>
    </AppLayout>
</template>
