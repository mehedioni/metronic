<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { PlusIcon, Trash2Icon } from 'lucide-vue-next';
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
import { Drawer } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useCsvExport } from '@/composables/useCsvExport';
import { usePageErrors } from '@/composables/usePageErrors';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, number } from '@/lib/format';
import { humanize } from '@/lib/status';
import inbound from '@/routes/inventory/inbound';
import type { Paginated } from '@/types';

interface ReceiptRow {
    id: string;
    reference_number: string;
    source: string;
    status: string;
    received_date: string | null;
    items_count: number;
    items_sum_quantity: number | null;
    supplier: { id: string; company_name: string } | null;
    received_by: { id: number; name: string } | null;
}

interface ProductOption {
    id: string;
    name: string;
    sku: string | null;
    variants: Array<{ id: string; sku: string; name: string }>;
}

const props = defineProps<{
    receipts: Paginated<ReceiptRow>;
    filters: Record<string, unknown>;
    options: {
        suppliers?: Array<{ id: string; company_name: string }>;
        products?: ProductOption[];
        sources?: string[];
        statuses?: string[];
    };
}>();

const { can } = usePermissions();
const { firstOf } = usePageErrors();
const { exportRows } = useCsvExport();

const { params, loading, toggleSort, sortState, reset } = useTableQuery({
    url: inbound.index.url(),
    filters: props.filters,
    only: ['receipts', 'filters'],
});

const rows = computed(() => props.receipts.data);
const creating = ref(false);

const form = useForm({
    supplier_id: '',
    source: 'supplier',
    received_date: '',
    notes: '',
    items: [
        {
            product_id: '',
            product_variant_id: '',
            quantity: 1,
            unit_cost: '' as string | number,
        },
    ],
});

const columns: Column[] = [
    {
        key: 'reference_number',
        label: 'Reference',
        sort: 'reference_number',
        width: '160px',
    },
    { key: 'supplier.company_name', label: 'Supplier', width: '220px' },
    { key: 'source', label: 'Source', sort: 'source', width: '140px' },
    {
        key: 'received_date',
        label: 'Received date',
        sort: 'received_date',
        width: '140px',
    },
    { key: 'items_count', label: 'Lines', align: 'center', width: '80px' },
    {
        key: 'items_sum_quantity',
        label: 'Units',
        align: 'center',
        width: '90px',
    },
    { key: 'status', label: 'Status', sort: 'status', width: '120px' },
];

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Inventory' },
    { label: 'Inbound Stock' },
];

/** Variants of the product chosen on a line, for its variant select. */
function variantsFor(productId: string) {
    return (
        props.options.products?.find((product) => product.id === productId)
            ?.variants ?? []
    );
}

function addLine() {
    form.items.push({
        product_id: '',
        product_variant_id: '',
        quantity: 1,
        unit_cost: '',
    });
}

function removeLine(index: number) {
    form.items.splice(index, 1);
}

function create() {
    form.post(inbound.store.url(), {
        onSuccess: () => {
            form.reset();
            creating.value = false;
        },
    });
}

function exportCurrent() {
    exportRows('inbound-receipts', rows.value, [
        { label: 'Reference', value: (row) => row.reference_number },
        { label: 'Supplier', value: (row) => row.supplier?.company_name ?? '' },
        { label: 'Source', value: (row) => row.source },
        { label: 'Received date', value: (row) => row.received_date ?? '' },
        { label: 'Lines', value: (row) => row.items_count },
        { label: 'Units', value: (row) => row.items_sum_quantity ?? 0 },
        { label: 'Status', value: (row) => row.status },
    ]);
}
</script>

<template>
    <Head title="Inbound stock" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="Inbound stock"
            :description="`${number(props.receipts.total)} receipts. Stock arrives only when a receipt is posted — creating one has no effect on the shelf.`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Button
                    v-if="can('inventory.create')"
                    size="dense"
                    @click="creating = true"
                >
                    <PlusIcon />
                    New receipt
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle>Receipts</CardTitle>
                </template>
            </CardHeader>

            <TableToolbar
                v-model:search="params.search"
                v-model:per-page="params.per_page"
                search-placeholder="Search reference"
                exportable
                @export="exportCurrent"
                @clear="reset"
            >
                <template #filters>
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
                </template>
            </TableToolbar>

            <DataTable
                :columns="columns"
                :rows="rows"
                :loading="loading"
                :sort-state="sortState"
                empty-title="No receipts"
                empty-description="Nothing matches these filters yet."
                @sort="toggleSort"
            >
                <template #cell-reference_number="{ row }">
                    <Link
                        :href="inbound.show.url(row.id)"
                        class="font-mono font-medium hover:underline"
                        >{{ row.reference_number }}</Link
                    >
                </template>

                <template #cell-supplier_company_name="{ row }">
                    <span class="text-muted-foreground">
                        {{ row.supplier?.company_name ?? '—' }}
                    </span>
                </template>

                <template #cell-source="{ row }">
                    {{ humanize(row.source) }}
                </template>

                <template #cell-received_date="{ row }">
                    <span class="text-muted-foreground">{{
                        date(row.received_date)
                    }}</span>
                </template>

                <template #cell-items_sum_quantity="{ row }">
                    {{ number(row.items_sum_quantity ?? 0) }}
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge :status="row.status" size="sm" />
                </template>

                <template #actions="{ row }">
                    <Button
                        variant="ghost"
                        size="dense"
                        as="a"
                        :href="inbound.show.url(row.id)"
                        >Open</Button
                    >
                </template>
            </DataTable>

            <Pagination
                :links="props.receipts.links"
                :from="props.receipts.from"
                :to="props.receipts.to"
                :total="props.receipts.total"
            />
        </Card>

        <Drawer
            :open="creating"
            title="New receipt"
            description="Creating a receipt records what is expected. Stock moves when it is posted."
            size="lg"
            @update:open="creating = $event"
        >
            <div class="space-y-5">
                <FormSection title="Receipt">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <FormField label="Supplier" :error="form.errors.supplier_id">
                            <Select v-model="form.supplier_id">
                                <option value="">No supplier</option>
                                <option
                                    v-for="supplier in props.options.suppliers ?? []"
                                    :key="supplier.id"
                                    :value="supplier.id"
                                >
                                    {{ supplier.company_name }}
                                </option>
                            </Select>
                        </FormField>

                        <FormField label="Source" :error="form.errors.source">
                            <Select v-model="form.source">
                                <option
                                    v-for="source in props.options.sources ?? []"
                                    :key="source"
                                    :value="source"
                                >
                                    {{ humanize(source) }}
                                </option>
                            </Select>
                        </FormField>

                        <FormField
                            label="Received date"
                            :error="form.errors.received_date"
                        >
                            <Input v-model="form.received_date" type="date" />
                        </FormField>
                    </div>
                </FormSection>

                <FormSection title="Lines">
                    <template #actions>
                        <Button
                            type="button"
                            variant="outline"
                            size="dense"
                            @click="addLine"
                        >
                            <PlusIcon />
                            Add line
                        </Button>
                    </template>

                    <div
                        v-for="(line, index) in form.items"
                        :key="index"
                        class="grid gap-3 rounded-md border border-border p-3 sm:grid-cols-[2fr_1.5fr_80px_100px_auto]"
                    >
                        <FormField
                            label="Product"
                            :error="form.errors[`items.${index}.product_id`]"
                        >
                            <Select v-model="line.product_id">
                                <option value="">Select product</option>
                                <option
                                    v-for="product in props.options.products ?? []"
                                    :key="product.id"
                                    :value="product.id"
                                >
                                    {{ product.name }}
                                </option>
                            </Select>
                        </FormField>

                        <FormField label="Variant">
                            <Select
                                v-model="line.product_variant_id"
                                :disabled="!variantsFor(line.product_id).length"
                            >
                                <option value="">Product itself</option>
                                <option
                                    v-for="variant in variantsFor(line.product_id)"
                                    :key="variant.id"
                                    :value="variant.id"
                                >
                                    {{ variant.name }}
                                </option>
                            </Select>
                        </FormField>

                        <FormField
                            label="Qty"
                            :error="form.errors[`items.${index}.quantity`]"
                        >
                            <Input v-model.number="line.quantity" type="number" min="1" />
                        </FormField>

                        <FormField label="Unit cost">
                            <Input v-model="line.unit_cost" type="number" step="0.01" />
                        </FormField>

                        <div class="flex items-end">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-dense"
                                aria-label="Remove line"
                                :disabled="form.items.length === 1"
                                @click="removeLine(index)"
                            >
                                <Trash2Icon />
                            </Button>
                        </div>
                    </div>

                    <p v-if="form.errors.items" class="text-[11px] text-danger">
                        {{ form.errors.items }}
                    </p>
                </FormSection>

                <p v-if="firstOf('inventory')" class="text-[11px] text-danger">
                    {{ firstOf('inventory') }}
                </p>
            </div>

            <template #footer>
                <Button variant="outline" size="dense" @click="creating = false">
                    Cancel
                </Button>
                <Button size="dense" :disabled="form.processing" @click="create">
                    Create receipt
                </Button>
            </template>
        </Drawer>
    </AppLayout>
</template>
