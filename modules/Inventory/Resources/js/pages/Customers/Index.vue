<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import CreateResourceCard from '@/components/CreateResourceCard.vue';
import DataCard from '@/components/DataCard.vue';
import Pagination from '@/components/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import routes from '@/routes/inventory/customers';
import type { Paginated } from '@/types';

const props = defineProps<{
    customers: Paginated<Record<string, any>>;
    filters: Record<string, unknown>;
    statuses: string[];
}>();

const { can } = usePermissions();

const filters = reactive({
    search: (props.filters.search as string) ?? '',
    status: (props.filters.status as string) ?? '',
});

watch(filters, (value) => {
    router.get(routes.index.url(), { ...value }, {
        preserveState: true,
        replace: true,
    });
});

const customerFields = [
    { name: 'name', label: 'Name', required: true },
    { name: 'email', label: 'Email', type: 'email' as const },
    { name: 'phone', label: 'Phone' },
    { name: 'city', label: 'City' },
    { name: 'country', label: 'Country code' },
];

/** Reads a dotted path off a row so the table stays declarative. */
function value(row: Record<string, any>, path: string): unknown {
    return path.split('.').reduce<any>((carry, key) => carry?.[key], row) ?? '—';
}

/** Lifetime spend divided by billable orders — the list's "Avg. spent" column. */
function averageSpent(row: Record<string, any>): string {
    const orders = Number(row.orders_count ?? 0);

    if (orders === 0) {
        return '—';
    }

    return (Number(row.total_spent ?? 0) / orders).toFixed(2);
}
</script>

<template>
    <Head title="Customers" />

    <AppLayout title="Customers">
        <div class="space-y-6">
            <CreateResourceCard
                v-if="can('customers.create')"
                title="New customer"
                action="/inventory/customers"
                :fields="customerFields"
            />

            <DataCard title="Filters">
                <div class="flex flex-wrap gap-3 p-4">
                    <input
                        v-model="filters.search"
                        placeholder="Search name, code, email, phone"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <select
                        v-model="filters.status"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option v-for="option in props.statuses" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                </div>
            </DataCard>

            <DataCard title="Customers">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-4 py-2">Code</th>
                                <th class="px-4 py-2">Customer</th>
                                <th class="px-4 py-2">Country</th>
                                <th class="px-4 py-2 text-right">Orders</th>
                                <th class="px-4 py-2 text-right">Total spent</th>
                                <th class="px-4 py-2 text-right">Avg. spent</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Last order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in props.customers.data"
                                :key="row.id"
                                class="border-t border-border"
                            >
                                <td class="px-4 py-2">{{ value(row, 'code') }}</td>
                                <td class="px-4 py-2">
                                    <Link :href="routes.show.url(row.id)" class="underline">{{
                                        value(row, 'name')
                                    }}</Link>
                                    <span class="block text-xs text-muted-foreground">{{
                                        value(row, 'email')
                                    }}</span>
                                </td>
                                <td class="px-4 py-2">{{ value(row, 'country') }}</td>
                                <td class="px-4 py-2 text-right">{{ row.orders_count ?? 0 }}</td>
                                <td class="px-4 py-2 text-right">{{ row.total_spent ?? 0 }}</td>
                                <td class="px-4 py-2 text-right">{{ averageSpent(row) }}</td>
                                <td class="px-4 py-2">{{ value(row, 'status') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'last_order_at') }}</td>
                            </tr>
                            <tr v-if="!props.customers.data.length">
                                <td class="px-4 py-3 text-muted-foreground" colspan="8">
                                    Nothing to show yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    :links="props.customers.links"
                    :from="props.customers.from"
                    :to="props.customers.to"
                    :total="props.customers.total"
                />
            </DataCard>
        </div>
    </AppLayout>
</template>
