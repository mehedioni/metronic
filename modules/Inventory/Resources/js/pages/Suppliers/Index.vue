<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { PlusIcon, PowerIcon, Trash2Icon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import { FormField, FormSection } from '@/components/form';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TableToolbar from '@/components/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Modal } from '@/components/ui/dialog';
import { Dropdown, DropdownItem } from '@/components/ui/dropdown';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useCsvExport } from '@/composables/useCsvExport';
import { usePageErrors } from '@/composables/usePageErrors';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { number } from '@/lib/format';
import supplierRoutes from '@/routes/inventory/suppliers';
import type { Paginated } from '@/types';

interface SupplierRow {
    id: string;
    code: string;
    company_name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    country: string | null;
    status: string;
    primary_products_count: number;
    inbound_receipts_count: number;
}

const props = defineProps<{
    suppliers: Paginated<SupplierRow>;
    filters: Record<string, unknown>;
    statuses: string[];
}>();

const { can } = usePermissions();
const { firstOf } = usePageErrors();
const { exportRows } = useCsvExport();

const { params, loading, toggleSort, sortState, reset } = useTableQuery({
    url: supplierRoutes.index.url(),
    filters: props.filters,
    only: ['suppliers', 'filters'],
});

const rows = computed(() => props.suppliers.data);
const confirming = ref<SupplierRow | null>(null);
const creating = ref(false);

const form = useForm({
    code: '',
    company_name: '',
    contact_name: '',
    email: '',
    phone: '',
    country: '',
    payment_terms: '',
});

const columns: Column[] = [
    { key: 'company_name', label: 'Supplier', sort: 'company_name', width: '240px' },
    { key: 'code', label: 'Code', sort: 'code', width: '120px' },
    { key: 'contact_name', label: 'Contact', sort: 'contact_name', width: '160px' },
    { key: 'email', label: 'Email', width: '200px' },
    { key: 'country', label: 'Country', sort: 'country', align: 'center', width: '100px' },
    {
        key: 'inbound_receipts_count',
        label: 'Receipts',
        align: 'center',
        width: '100px',
    },
    { key: 'status', label: 'Status', sort: 'status', width: '110px' },
];

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Suppliers' },
    { label: 'Supplier List' },
];

function create() {
    form.post(supplierRoutes.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            creating.value = false;
        },
    });
}

function toggleStatus(row: SupplierRow) {
    router.patch(`/inventory/suppliers/${row.id}/status`, {}, {
        preserveScroll: true,
    });
}

function destroy() {
    if (!confirming.value) {
        return;
    }

    router.delete(supplierRoutes.destroy.url(confirming.value.id), {
        preserveScroll: true,
        onFinish: () => (confirming.value = null),
    });
}

function exportCurrent() {
    exportRows('suppliers', rows.value, [
        { label: 'Company', value: (row) => row.company_name },
        { label: 'Code', value: (row) => row.code },
        { label: 'Contact', value: (row) => row.contact_name ?? '' },
        { label: 'Email', value: (row) => row.email ?? '' },
        { label: 'Phone', value: (row) => row.phone ?? '' },
        { label: 'Country', value: (row) => row.country ?? '' },
        { label: 'Receipts', value: (row) => row.inbound_receipts_count },
        { label: 'Status', value: (row) => row.status },
    ]);
}
</script>

<template>
    <Head title="Suppliers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="Suppliers"
            :description="`${number(props.suppliers.total)} suppliers`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Button
                    v-if="can('suppliers.create')"
                    size="dense"
                    @click="creating = true"
                >
                    <PlusIcon />
                    New supplier
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle>Supplier list</CardTitle>
                </template>
            </CardHeader>

            <TableToolbar
                v-model:search="params.search"
                v-model:per-page="params.per_page"
                search-placeholder="Search company, code, contact"
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
                empty-title="No suppliers"
                empty-description="Nothing matches these filters yet."
                @sort="toggleSort"
            >
                <template #cell-company_name="{ row }">
                    <Link
                        :href="supplierRoutes.show.url(row.id)"
                        class="font-medium hover:underline"
                        >{{ row.company_name }}</Link
                    >
                </template>

                <template #cell-code="{ row }">
                    <span class="font-mono text-[11px] text-muted-foreground">{{
                        row.code
                    }}</span>
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge :status="row.status" size="sm" />
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
                                :href="supplierRoutes.show.url(row.id)"
                                class="flex w-full items-center gap-2"
                                >View</Link
                            >
                        </DropdownItem>

                        <DropdownItem
                            v-if="can('suppliers.update')"
                            @select="toggleStatus(row)"
                        >
                            <PowerIcon />
                            {{ row.status === 'active' ? 'Deactivate' : 'Activate' }}
                        </DropdownItem>

                        <DropdownItem
                            v-if="can('suppliers.delete')"
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
                :links="props.suppliers.links"
                :from="props.suppliers.from"
                :to="props.suppliers.to"
                :total="props.suppliers.total"
            />
        </Card>

        <Modal
            :open="creating"
            title="New supplier"
            description="Supplier terms per product are set on the product itself."
            @update:open="creating = $event"
        >
            <FormSection title="Supplier">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Code" :error="form.errors.code" required>
                        <Input v-model="form.code" class="font-mono" />
                    </FormField>

                    <FormField
                        label="Company name"
                        :error="form.errors.company_name"
                        required
                    >
                        <Input v-model="form.company_name" />
                    </FormField>

                    <FormField label="Contact name" :error="form.errors.contact_name">
                        <Input v-model="form.contact_name" />
                    </FormField>

                    <FormField label="Email" :error="form.errors.email">
                        <Input v-model="form.email" type="email" />
                    </FormField>

                    <FormField label="Phone" :error="form.errors.phone">
                        <Input v-model="form.phone" />
                    </FormField>

                    <FormField
                        label="Country"
                        :error="form.errors.country"
                        hint="Two-letter code, e.g. US"
                    >
                        <Input v-model="form.country" maxlength="2" />
                    </FormField>

                    <FormField
                        label="Payment terms"
                        :error="form.errors.payment_terms"
                        class="sm:col-span-2"
                    >
                        <Input v-model="form.payment_terms" placeholder="Net 30" />
                    </FormField>
                </div>
            </FormSection>

            <template #footer>
                <Button variant="outline" size="dense" @click="creating = false">
                    Cancel
                </Button>
                <Button size="dense" :disabled="form.processing" @click="create">
                    Create supplier
                </Button>
            </template>
        </Modal>

        <Modal
            :open="Boolean(confirming)"
            title="Delete supplier"
            size="sm"
            @update:open="confirming = null"
        >
            <p class="text-[0.8125rem] text-muted-foreground">
                A supplier with receiving history is never deleted — deactivate
                it instead, so the history keeps resolving.
            </p>

            <p v-if="firstOf('inventory')" class="mt-3 text-[11px] text-danger">
                {{ firstOf('inventory') }}
            </p>

            <template #footer>
                <Button variant="outline" size="dense" @click="confirming = null">
                    Cancel
                </Button>
                <Button variant="destructive" size="dense" @click="destroy">
                    Delete supplier
                </Button>
            </template>
        </Modal>
    </AppLayout>
</template>
