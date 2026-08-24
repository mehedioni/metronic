<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableToolbar from '@/components/TableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { useCsvExport } from '@/composables/useCsvExport';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { number } from '@/lib/format';
import products from '@/routes/inventory/products';
import stock from '@/routes/inventory/stock';
import type { Paginated } from '@/types';

interface Plan {
    target_level: number;
    available: number;
    delta: number;
    daily_velocity: number;
    days_of_cover: number | null;
    lead_time_days: number;
    reorder_quantity: number;
    needs_reorder: boolean;
}

interface PlannerRow {
    id: string;
    product_id: string;
    quantity_on_hand: number;
    quantity_reserved: number;
    plan: Plan;
    product: { id: string; name: string; sku: string | null };
    variant: { id: string; sku: string; name: string } | null;
}

const props = defineProps<{
    items: Paginated<PlannerRow>;
    filters: Record<string, unknown>;
    categories: Array<{ id: string; name: string }>;
    summary?: {
        units_tracked: number;
        below_target: number;
        out_of_stock: number;
        fully_reserved: number;
    };
}>();

const { params, loading, toggleSort, sortState, reset } = useTableQuery({
    url: stock.planner.url(),
    filters: props.filters,
    only: ['items', 'filters'],
});

const { exportRows } = useCsvExport();

const columns: Column[] = [
    { key: 'product.name', label: 'Product', width: '260px' },
    {
        key: 'quantity_on_hand',
        label: 'Stock',
        sort: 'quantity_on_hand',
        align: 'center',
        width: '80px',
    },
    {
        key: 'quantity_reserved',
        label: 'Rsvd',
        sort: 'quantity_reserved',
        align: 'center',
        width: '80px',
    },
    { key: 'plan.target_level', label: 'T. Lvl', align: 'center', width: '80px' },
    { key: 'plan.delta', label: 'Delta', align: 'center', width: '80px' },
    { key: 'plan.daily_velocity', label: 'Flow', width: '90px' },
    { key: 'plan.days_of_cover', label: 'Reorder in', width: '120px' },
    {
        key: 'plan.reorder_quantity',
        label: 'Reorder',
        align: 'center',
        width: '90px',
    },
    { key: 'plan.lead_time_days', label: 'Lead time', width: '110px' },
];

const rows = computed(() => props.items.data);

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Inventory' },
    { label: 'Stock Planner' },
];

/** Days of cover, phrased the way the design's "Reorder In" column reads. */
function coverLabel(plan: Plan): string {
    if (plan.days_of_cover === null) {
        return 'No movement';
    }

    if (plan.days_of_cover === 0) {
        return 'Now';
    }

    return `${plan.days_of_cover} day${plan.days_of_cover === 1 ? '' : 's'}`;
}

function coverVariant(plan: Plan) {
    if (plan.days_of_cover === null) {
        return 'neutral' as const;
    }

    if (plan.days_of_cover <= plan.lead_time_days) {
        return 'danger' as const;
    }

    if (plan.days_of_cover <= plan.lead_time_days * 2) {
        return 'warning' as const;
    }

    return 'success' as const;
}

function exportCurrent() {
    exportRows('stock-planner', rows.value, [
        { label: 'Product', value: (row) => row.product.name },
        { label: 'Variant', value: (row) => row.variant?.name ?? '' },
        { label: 'SKU', value: (row) => row.variant?.sku ?? row.product.sku ?? '' },
        { label: 'On hand', value: (row) => row.quantity_on_hand },
        { label: 'Reserved', value: (row) => row.quantity_reserved },
        { label: 'Available', value: (row) => row.plan.available },
        { label: 'Target level', value: (row) => row.plan.target_level },
        { label: 'Daily velocity', value: (row) => row.plan.daily_velocity },
        { label: 'Days of cover', value: (row) => row.plan.days_of_cover ?? '' },
        { label: 'Lead time (days)', value: (row) => row.plan.lead_time_days },
        { label: 'Suggested reorder', value: (row) => row.plan.reorder_quantity },
    ]);
}
</script>

<template>
    <Head title="Stock planner" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="Stock planner"
            description="Reorder suggestions derived from the stock ledger, each unit's target level and its supplier lead time."
            :breadcrumbs="breadcrumbs"
        />

        <Deferred data="summary">
            <template #fallback>
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="index in 4"
                        :key="index"
                        class="h-24 animate-pulse rounded-xl bg-muted/40"
                    />
                </div>
            </template>

            <div v-if="summary" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <Card v-for="tile in [
                    { label: 'Units tracked', value: summary.units_tracked },
                    { label: 'Below target', value: summary.below_target },
                    { label: 'Out of stock', value: summary.out_of_stock },
                    { label: 'Fully reserved', value: summary.fully_reserved },
                ]" :key="tile.label" class="p-5">
                    <p class="text-xs text-muted-foreground">{{ tile.label }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight">
                        {{ number(tile.value) }}
                    </p>
                </Card>
            </div>
        </Deferred>

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle
                        :description="`${props.items.total} stockable units`"
                        >Reorder plan</CardTitle
                    >
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

                    <Select v-model="params.reorder_within" class="w-44">
                        <option value="">Any horizon</option>
                        <option value="7">Reorder in 7 days</option>
                        <option value="14">Reorder in 14 days</option>
                        <option value="30">Reorder in 30 days</option>
                    </Select>
                </template>
            </TableToolbar>

            <DataTable
                :columns="columns"
                :rows="rows"
                :loading="loading"
                :sort-state="sortState"
                empty-title="Nothing to plan"
                empty-description="No stockable unit matches these filters."
                @sort="toggleSort"
            >
                <template #cell-product_name="{ row }">
                    <Link
                        :href="products.show.url(row.product_id)"
                        class="font-medium text-foreground hover:underline"
                    >
                        {{ row.product.name }}
                    </Link>
                    <span class="block font-mono text-[11px] text-muted-foreground">
                        {{ row.variant?.sku ?? row.product.sku ?? '—' }}
                        <template v-if="row.variant"> · {{ row.variant.name }}</template>
                    </span>
                </template>

                <template #cell-plan_delta="{ row }">
                    <span
                        :class="
                            row.plan.delta < 0
                                ? 'font-medium text-danger'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ row.plan.delta > 0 ? '+' : '' }}{{ row.plan.delta }}
                    </span>
                </template>

                <template #cell-plan_daily_velocity="{ row }">
                    <span class="text-muted-foreground">
                        {{ row.plan.daily_velocity }} / day
                    </span>
                </template>

                <template #cell-plan_days_of_cover="{ row }">
                    <Badge :variant="coverVariant(row.plan)" size="sm">
                        {{ coverLabel(row.plan) }}
                    </Badge>
                </template>

                <template #cell-plan_reorder_quantity="{ row }">
                    <span
                        :class="
                            row.plan.needs_reorder
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ row.plan.reorder_quantity || '—' }}
                    </span>
                </template>

                <template #cell-plan_lead_time_days="{ row }">
                    <span class="text-muted-foreground">
                        {{ row.plan.lead_time_days }} days
                    </span>
                </template>
            </DataTable>

            <Pagination
                :links="props.items.links"
                :from="props.items.from"
                :to="props.items.to"
                :total="props.items.total"
            />
        </Card>
    </AppLayout>
</template>
