<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import CreateResourceCard from '@/components/CreateResourceCard.vue';
import DataCard from '@/components/DataCard.vue';
import Pagination from '@/components/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import routes from '@/routes/inventory/suppliers';
import type { Paginated } from '@/types';

const props = defineProps<{
    suppliers: Paginated<Record<string, any>>;
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

const supplierFields = [
    { name: 'code', label: 'Supplier code', required: true },
    { name: 'company_name', label: 'Company name', required: true },
    { name: 'contact_name', label: 'Contact name' },
    { name: 'email', label: 'Email', type: 'email' as const },
    { name: 'phone', label: 'Phone' },
    { name: 'payment_terms', label: 'Payment terms' },
];

/** Reads a dotted path off a row so the table stays declarative. */
function value(row: Record<string, any>, path: string): unknown {
    return path.split('.').reduce<any>((carry, key) => carry?.[key], row) ?? '—';
}
</script>

<template>
    <Head title="Suppliers" />

    <AppLayout title="Suppliers">
        <div class="space-y-6">
            <CreateResourceCard
                v-if="can('suppliers.create')"
                title="New supplier"
                action="/inventory/suppliers"
                :fields="supplierFields"
            />

            <DataCard title="Filters">
                <div class="flex flex-wrap gap-3 p-4">
                    <input
                        v-model="filters.search"
                        placeholder="Search company, code, contact, email, phone"
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

            <DataCard title="Suppliers">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-4 py-2">Company</th>
                                <th class="px-4 py-2">Code</th>
                                <th class="px-4 py-2">Contact</th>
                                <th class="px-4 py-2">Email</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Receipts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in props.suppliers.data"
                                :key="row.id"
                                class="border-t border-border"
                            >
                                <td class="px-4 py-2">
                                    <Link
                                        :href="routes.show.url(row.id)"
                                        class="underline"
                                        >{{ value(row, 'company_name') }}</Link
                                    >
                                </td>
                                <td class="px-4 py-2">{{ value(row, 'code') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'contact_name') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'email') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'status') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'inbound_receipts_count') }}</td>
                            </tr>
                            <tr v-if="!props.suppliers.data.length">
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
                    :links="props.suppliers.links"
                    :from="props.suppliers.from"
                    :to="props.suppliers.to"
                    :total="props.suppliers.total"
                />
            </DataCard>
        </div>
    </AppLayout>
</template>
