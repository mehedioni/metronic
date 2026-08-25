<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { PlusIcon, PowerIcon, Trash2Icon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import { FormField, FormSection } from '@/components/form';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TableToolbar from '@/components/TableToolbar.vue';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Drawer } from '@/components/ui/drawer';
import { Dropdown, DropdownItem } from '@/components/ui/dropdown';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useCsvExport } from '@/composables/useCsvExport';
import { usePageErrors } from '@/composables/usePageErrors';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, money, number } from '@/lib/format';
import customerRoutes from '@/routes/inventory/customers';
import type { Paginated } from '@/types';

interface CustomerRow {
    id: number;
    code: string;
    name: string;
    email: string | null;
    phone: string | null;
    country: string | null;
    status: string;
    /** Aggregates summed from the orders table by CustomerService. */
    orders_count: number;
    total_spent: number | string | null;
    last_order_at: string | null;
}

const props = defineProps<{
    customers: Paginated<CustomerRow>;
    filters: Record<string, unknown>;
    statuses: string[];
}>();

const { can } = usePermissions();
const { firstOf } = usePageErrors();
const { exportRows } = useCsvExport();

const { params, loading, toggleSort, sortState, reset } = useTableQuery({
    url: customerRoutes.index.url(),
    filters: props.filters,
    only: ['customers', 'filters'],
});

const rows = computed(() => props.customers.data);
const confirming = ref<CustomerRow | null>(null);
const creating = ref(false);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    city: '',
    country: '',
});

const columns: Column[] = [
    { key: 'code', label: 'Customer ID', sort: 'code', width: '130px' },
    { key: 'name', label: 'Customer', sort: 'name', width: '240px' },
    { key: 'country', label: 'Country', sort: 'country', align: 'center', width: '100px' },
    {
        key: 'orders_count',
        label: 'Orders',
        sort: 'orders_count',
        align: 'end',
        width: '90px',
    },
    {
        key: 'total_spent',
        label: 'Total spent',
        sort: 'total_spent',
        align: 'end',
        width: '130px',
    },
    { key: 'average', label: 'Avg. spent', align: 'end', width: '130px' },
    { key: 'status', label: 'Status', sort: 'status', width: '110px' },
    {
        key: 'last_order_at',
        label: 'Last order',
        sort: 'last_order_at',
        width: '130px',
    },
];

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Customers' },
    { label: 'Customer List' },
];

/** Lifetime spend over billable orders; blank when they have none. */
function averageSpent(row: CustomerRow): string {
    if (!row.orders_count) {
        return '—';
    }

    return money(Number(row.total_spent ?? 0) / row.orders_count);
}

function create() {
    form.post(customerRoutes.store.url(), {
        onSuccess: () => {
            form.reset();
            creating.value = false;
        },
    });
}

function toggleStatus(row: CustomerRow) {
    router.patch(`/inventory/customers/${row.id}/status`, {}, {
        preserveScroll: true,
    });
}

function destroy() {
    if (!confirming.value) {
        return;
    }

    router.delete(customerRoutes.destroy.url(confirming.value.id), {
        preserveScroll: true,
        onFinish: () => (confirming.value = null),
    });
}

function exportCurrent() {
    exportRows('customers', rows.value, [
        { label: 'Customer ID', value: (row) => row.code },
        { label: 'Name', value: (row) => row.name },
        { label: 'Email', value: (row) => row.email ?? '' },
        { label: 'Phone', value: (row) => row.phone ?? '' },
        { label: 'Country', value: (row) => row.country ?? '' },
        { label: 'Orders', value: (row) => row.orders_count },
        { label: 'Total spent', value: (row) => row.total_spent ?? 0 },
        { label: 'Status', value: (row) => row.status },
        { label: 'Last order', value: (row) => row.last_order_at ?? '' },
    ]);
}
</script>

<template>
    <Head title="Customers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="Customers"
            :description="`${number(props.customers.total)} customers. Spend is summed from their orders, cancelled ones excluded.`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Button
                    v-if="can('customers.create')"
                    size="dense"
                    @click="creating = true"
                >
                    <PlusIcon />
                    New customer
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle>Customer list</CardTitle>
                </template>
            </CardHeader>

            <TableToolbar
                v-model:search="params.search"
                v-model:per-page="params.per_page"
                search-placeholder="Search name, code, email, phone"
                exportable
                @export="exportCurrent"
                @clear="reset"
            >
                <template #filters>
                    <Select v-model="params.status" class="w-36">
                        <option value="">All statuses</option>
                        <option
                            v-for="status in props.statuses"
                            :key="status"
                            :value="status"
                        >
                            {{ status }}
                        </option>
                    </Select>
                </template>
            </TableToolbar>

            <DataTable
                :columns="columns"
                :rows="rows"
                :loading="loading"
                :sort-state="sortState"
                selectable
                empty-title="No customers"
                empty-description="Nothing matches these filters yet."
                @sort="toggleSort"
            >
                <template #cell-code="{ row }">
                    <span class="font-mono text-[11px] font-semibold">{{
                        row.code
                    }}</span>
                </template>

                <template #cell-name="{ row }">
                    <span class="flex items-center gap-2.5">
                        <Avatar :name="row.name" class="size-7" />
                        <span class="min-w-0">
                            <Link
                                :href="customerRoutes.show.url(row.id)"
                                class="block truncate font-medium hover:underline"
                                >{{ row.name }}</Link
                            >
                            <span
                                class="block truncate text-[11px] text-muted-foreground"
                                >{{ row.email ?? 'No email' }}</span
                            >
                        </span>
                    </span>
                </template>

                <template #cell-total_spent="{ row }">
                    {{ money(row.total_spent ?? 0) }}
                </template>

                <template #cell-average="{ row }">
                    <span class="text-muted-foreground">{{ averageSpent(row) }}</span>
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge :status="row.status" size="sm" />
                </template>

                <template #cell-last_order_at="{ row }">
                    <span class="text-muted-foreground">{{
                        date(row.last_order_at)
                    }}</span>
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
                                :href="customerRoutes.show.url(row.id)"
                                class="flex w-full items-center gap-2"
                                >View</Link
                            >
                        </DropdownItem>

                        <DropdownItem
                            v-if="can('customers.update')"
                            @select="toggleStatus(row)"
                        >
                            <PowerIcon />
                            {{ row.status === 'active' ? 'Deactivate' : 'Activate' }}
                        </DropdownItem>

                        <DropdownItem
                            v-if="can('customers.delete')"
                            destructive
                            @select="confirming = row"
                        >
                            <Trash2Icon />
                            Delete
                        </DropdownItem>
                    </Dropdown>
                </template>
            </DataTable>

            <Pagination
                :links="props.customers.links"
                :from="props.customers.from"
                :to="props.customers.to"
                :total="props.customers.total"
            />
        </Card>

        <Drawer
            :open="creating"
            title="New customer"
            description="A code is generated automatically."
            @update:open="creating = $event"
        >
            <FormSection title="Customer">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Name"
                        :error="form.errors.name"
                        required
                        class="sm:col-span-2"
                    >
                        <Input v-model="form.name" />
                    </FormField>

                    <FormField label="Email" :error="form.errors.email">
                        <Input v-model="form.email" type="email" />
                    </FormField>

                    <FormField label="Phone" :error="form.errors.phone">
                        <Input v-model="form.phone" />
                    </FormField>

                    <FormField label="City" :error="form.errors.city">
                        <Input v-model="form.city" />
                    </FormField>

                    <FormField
                        label="Country"
                        :error="form.errors.country"
                        hint="Two-letter code"
                    >
                        <Input v-model="form.country" maxlength="2" />
                    </FormField>
                </div>
            </FormSection>

            <template #footer>
                <Button variant="outline" size="dense" @click="creating = false">
                    Cancel
                </Button>
                <Button size="dense" :disabled="form.processing" @click="create">
                    Create customer
                </Button>
            </template>
        </Drawer>

        <Drawer
            :open="Boolean(confirming)"
            title="Delete customer"
            size="sm"
            @update:open="confirming = null"
        >
            <p class="text-[0.8125rem] text-muted-foreground">
                A customer with orders is never deleted — deactivate them
                instead, so those orders keep resolving to a customer.
            </p>

            <p v-if="firstOf('inventory')" class="mt-3 text-[11px] text-danger">
                {{ firstOf('inventory') }}
            </p>

            <template #footer>
                <Button variant="outline" size="dense" @click="confirming = null">
                    Cancel
                </Button>
                <Button variant="destructive" size="dense" @click="destroy">
                    Delete customer
                </Button>
            </template>
        </Drawer>
    </AppLayout>
</template>
