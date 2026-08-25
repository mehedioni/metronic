<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PlusIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TableToolbar from '@/components/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useCsvExport } from '@/composables/useCsvExport';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, money, number } from '@/lib/format';
import customers from '@/routes/inventory/customers';
import orderRoutes from '@/routes/inventory/orders';
import type { Paginated } from '@/types';

interface OrderRow {
    id: string;
    order_number: string;
    customer_name: string;
    customer_email: string | null;
    status: string;
    total: string;
    currency: string;
    items_count: number;
    created_at: string;
    customer: { id: string; code: string; name: string } | null;
    created_by: { id: number; name: string } | null;
}

const props = defineProps<{
    orders: Paginated<OrderRow>;
    filters: Record<string, unknown>;
    options: {
        statuses?: string[];
        customers?: Array<{ id: string; name: string }>;
    };
}>();

const { can } = usePermissions();
const { exportRows } = useCsvExport();

const { params, loading, toggleSort, sortState, reset } = useTableQuery({
    url: orderRoutes.index.url(),
    filters: props.filters,
    only: ['orders', 'filters'],
});

const rows = computed(() => props.orders.data);
const selected = ref<OrderRow[]>([]);

const columns: Column[] = [
    { key: 'order_number', label: 'Order', sort: 'order_number', width: '150px' },
    { key: 'created_at', label: 'Date', sort: 'created_at', width: '130px' },
    {
        key: 'customer_name',
        label: 'Customer',
        sort: 'customer_name',
        width: '220px',
    },
    { key: 'items_count', label: 'Items', align: 'center', width: '80px' },
    { key: 'total', label: 'Total', sort: 'total', align: 'end', width: '130px' },
    { key: 'status', label: 'Status', sort: 'status', width: '120px' },
    { key: 'created_by.name', label: 'Created by', width: '150px' },
];

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Orders' },
    { label: 'Order List' },
];

function exportCurrent() {
    const source = selected.value.length ? selected.value : rows.value;

    exportRows('orders', source, [
        { label: 'Order', value: (row) => row.order_number },
        { label: 'Date', value: (row) => row.created_at },
        { label: 'Customer', value: (row) => row.customer_name },
        { label: 'Email', value: (row) => row.customer_email ?? '' },
        { label: 'Items', value: (row) => row.items_count },
        { label: 'Total', value: (row) => row.total },
        { label: 'Currency', value: (row) => row.currency },
        { label: 'Status', value: (row) => row.status },
    ]);
}
</script>

<template>
    <Head title="Orders" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="Orders"
            :description="`${number(props.orders.total)} orders`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Button
                    v-if="can('orders.create')"
                    size="dense"
                    as="a"
                    :href="orderRoutes.create.url()"
                >
                    <PlusIcon />
                    Take order
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle>Order list</CardTitle>
                </template>
            </CardHeader>

            <TableToolbar
                v-model:search="params.search"
                v-model:per-page="params.per_page"
                search-placeholder="Search order number, customer, email"
                exportable
                :selected-count="selected.length"
                @export="exportCurrent"
                @clear="reset"
            >
                <template #filters>
                    <Select v-model="params.status" class="w-40">
                        <option value="">All statuses</option>
                        <option
                            v-for="status in props.options.statuses ?? []"
                            :key="status"
                            :value="status"
                        >
                            {{ status }}
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
                selectable
                empty-title="No orders"
                empty-description="Nothing matches these filters yet."
                @sort="toggleSort"
                @selection-change="selected = $event as OrderRow[]"
            >
                <template v-if="can('orders.create')" #empty-action>
                    <Button size="dense" as="a" :href="orderRoutes.create.url()">
                        <PlusIcon />
                        Take order
                    </Button>
                </template>

                <template #cell-order_number="{ row }">
                    <Link
                        :href="orderRoutes.show.url(row.id)"
                        class="font-mono font-medium hover:underline"
                        >{{ row.order_number }}</Link
                    >
                </template>

                <template #cell-created_at="{ row }">
                    <span class="text-muted-foreground">{{
                        date(row.created_at)
                    }}</span>
                </template>

                <template #cell-customer_name="{ row }">
                    <Link
                        v-if="row.customer"
                        :href="customers.show.url(row.customer.id)"
                        class="font-medium hover:underline"
                        >{{ row.customer_name }}</Link
                    >
                    <span v-else class="font-medium">{{ row.customer_name }}</span>
                    <span class="block text-[11px] text-muted-foreground">
                        {{ row.customer_email ?? 'Walk-in' }}
                    </span>
                </template>

                <template #cell-total="{ row }">
                    <span class="font-medium">{{
                        money(row.total, row.currency)
                    }}</span>
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge :status="row.status" size="sm" />
                </template>

                <template #cell-created_by_name="{ row }">
                    <span class="text-muted-foreground">{{
                        row.created_by?.name ?? '—'
                    }}</span>
                </template>

                <template #actions="{ row }">
                    <Button
                        variant="ghost"
                        size="dense"
                        as="a"
                        :href="orderRoutes.show.url(row.id)"
                        >Open</Button
                    >
                </template>
            </DataTable>

            <Pagination
                :links="props.orders.links"
                :from="props.orders.from"
                :to="props.orders.to"
                :total="props.orders.total"
            />
        </Card>
    </AppLayout>
</template>
