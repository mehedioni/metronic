<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PlusIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableToolbar from '@/components/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useCsvExport } from '@/composables/useCsvExport';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, money, number } from '@/lib/format';
import customers from '@/routes/inventory/customers';
import orderRoutes from '@/routes/inventory/orders';
import quoteRoutes from '@/routes/inventory/quotes';
import type { Paginated } from '@/types';

interface OrderStatus {
    id: number;
    key: string;
    label: string;
    variant: string;
}

interface QuoteRow {
    id: number;
    order_number: string;
    customer_name: string;
    customer_email: string | null;
    customer_phone: string | null;
    status: OrderStatus;
    total: string;
    items_count: number;
    created_at: string;
    customer: { id: number; code: string; name: string } | null;
    created_by: { id: number; name: string } | null;
}

const props = defineProps<{
    quotes: Paginated<QuoteRow>;
    filters: Record<string, unknown>;
    status: OrderStatus;
    options: Record<string, any>;
}>();

const { can } = usePermissions();
const { exportRows } = useCsvExport();

const { params, loading, toggleSort, sortState, reset } = useTableQuery({
    url: quoteRoutes.index.url(),
    filters: props.filters,
    only: ['quotes', 'filters'],
});

const rows = computed(() => props.quotes.data);

const columns: Column[] = [
    { key: 'order_number', label: 'Quote', sort: 'order_number', width: '150px' },
    { key: 'created_at', label: 'Date', sort: 'created_at', width: '130px' },
    { key: 'customer_name', label: 'Customer', sort: 'customer_name', width: '240px' },
    { key: 'items_count', label: 'Items', align: 'center', width: '80px' },
    { key: 'total', label: 'Total', sort: 'total', align: 'end', width: '130px' },
    { key: 'created_by.name', label: 'Created by', width: '150px' },
];

const breadcrumbs = computed(() => [
    { label: 'Store Inventory' },
    { label: 'Orders', href: orderRoutes.index.url() },
    { label: `${props.status.label}s` },
]);

function exportCurrent() {
    exportRows('quotes', rows.value, [
        { label: 'Quote', value: (row) => row.order_number },
        { label: 'Date', value: (row) => row.created_at },
        { label: 'Customer', value: (row) => row.customer_name },
        { label: 'Email', value: (row) => row.customer_email ?? '' },
        { label: 'Phone', value: (row) => row.customer_phone ?? '' },
        { label: 'Items', value: (row) => row.items_count },
        { label: 'Total', value: (row) => row.total },
    ]);
}
</script>

<template>
    <Head :title="`${props.status.label}s`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            :title="`${props.status.label}s`"
            :description="`${number(props.quotes.total)} orders sitting in the ${props.status.label.toLowerCase()} status. Nothing here reserves stock — confirming one does.`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Button
                    v-if="can('orders.create')"
                    size="dense"
                    as="a"
                    :href="quoteRoutes.create.url()"
                >
                    <PlusIcon />
                    New {{ props.status.label.toLowerCase() }}
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle
                        :description="`A ${props.status.label.toLowerCase()} keeps its number and its lines when it is confirmed — it becomes the order, rather than being copied into one.`"
                        >{{ props.status.label }} list</CardTitle
                    >
                </template>
            </CardHeader>

            <TableToolbar
                v-model:search="params.search"
                v-model:per-page="params.per_page"
                search-placeholder="Search number, customer, email"
                exportable
                @export="exportCurrent"
                @clear="reset"
            >
                <template #filters>
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
                :empty-title="`No ${props.status.label.toLowerCase()}s`"
                empty-description="Nothing matches these filters yet."
                @sort="toggleSort"
            >
                <template #cell-order_number="{ row }">
                    <Link
                        :href="orderRoutes.show.url(row.id)"
                        class="font-mono font-medium hover:underline"
                        >{{ row.order_number }}</Link
                    >
                </template>

                <template #cell-created_at="{ row }">
                    <span class="text-muted-foreground">{{ date(row.created_at) }}</span>
                </template>

                <template #cell-customer_name="{ row }">
                    <Link
                        v-if="row.customer"
                        :href="customers.show.url(row.customer.id)"
                        class="font-medium hover:underline"
                        >{{ row.customer_name }}</Link
                    >
                    <span v-else class="font-medium">{{ row.customer_name }}</span>
                    <span class="block text-2xs text-muted-foreground">
                        {{ row.customer_phone ?? '—' }}
                        <template v-if="row.customer_email">
                            · {{ row.customer_email }}
                        </template>
                    </span>
                </template>

                <template #cell-total="{ row }">
                    <span class="font-medium">{{ money(row.total) }}</span>
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

                <template v-if="can('orders.create')" #empty-action>
                    <Button size="dense" as="a" :href="quoteRoutes.create.url()">
                        <PlusIcon />
                        New {{ props.status.label.toLowerCase() }}
                    </Button>
                </template>
            </DataTable>

            <Pagination
                :links="props.quotes.links"
                :from="props.quotes.from"
                :to="props.quotes.to"
                :total="props.quotes.total"
            />
        </Card>
    </AppLayout>
</template>
