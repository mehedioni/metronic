<script setup lang="ts">
import { Deferred, Head, router, useForm } from '@inertiajs/vue3';
import { PencilIcon, PlusIcon, Trash2Icon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import { FormField, FormSection, Textarea } from '@/components/form';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableToolbar from '@/components/TableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Drawer } from '@/components/ui/drawer';
import { Dropdown, DropdownItem } from '@/components/ui/dropdown';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useCsvExport } from '@/composables/useCsvExport';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, money, number } from '@/lib/format';
import { humanize } from '@/lib/status';
import expenseRoutes from '@/routes/inventory/expenses';
import reports from '@/routes/inventory/reports';
import type { Paginated } from '@/types';

interface ExpenseRow {
    id: number;
    spent_on: string;
    category: string;
    amount: string;
    currency: string;
    reference: string | null;
    description: string | null;
    supplier: { id: number; company_name: string } | null;
    created_by: { id: number; name: string } | null;
}

const props = defineProps<{
    expenses: Paginated<ExpenseRow>;
    filters: Record<string, unknown>;
    categories: string[];
    suppliers: Array<{ id: number; company_name: string }>;
    summary?: {
        count: number;
        total: number;
        by_category: Record<string, number>;
    };
}>();

const { can } = usePermissions();
const { exportRows } = useCsvExport();

const { params, loading, toggleSort, sortState, reset } = useTableQuery({
    url: expenseRoutes.index.url(),
    filters: props.filters,
    only: ['expenses', 'filters', 'summary'],
});

const rows = computed(() => props.expenses.data);

/** null = closed, an id = editing, 'new' = recording a fresh one. */
const editing = ref<ExpenseRow | 'new' | null>(null);
const confirming = ref<ExpenseRow | null>(null);

const form = useForm({
    spent_on: new Date().toISOString().slice(0, 10),
    category: 'other',
    amount: '' as string | number,
    currency: 'USD',
    reference: '',
    supplier_id: '',
    description: '',
});

const columns: Column[] = [
    { key: 'spent_on', label: 'Date', sort: 'spent_on', width: '130px' },
    { key: 'category', label: 'Category', sort: 'category', width: '140px' },
    { key: 'amount', label: 'Amount', sort: 'amount', align: 'end', width: '130px' },
    { key: 'reference', label: 'Reference', width: '140px' },
    { key: 'description', label: 'Description', width: '260px' },
    { key: 'supplier.company_name', label: 'Paid to', width: '180px' },
];

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Expenses' },
];

function openCreate() {
    form.reset();
    form.clearErrors();
    editing.value = 'new';
}

function openEdit(row: ExpenseRow) {
    form.spent_on = row.spent_on.slice(0, 10);
    form.category = row.category;
    form.amount = row.amount;
    form.currency = row.currency;
    form.reference = row.reference ?? '';
    form.supplier_id = row.supplier ? String(row.supplier.id) : '';
    form.description = row.description ?? '';
    form.clearErrors();

    editing.value = row;
}

function save() {
    if (editing.value === 'new') {
        form.post(expenseRoutes.store.url(), {
            preserveScroll: true,
            onSuccess: () => (editing.value = null),
        });

        return;
    }

    if (!editing.value) {
        return;
    }

    form.put(expenseRoutes.update.url(editing.value.id), {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
}

function destroy() {
    if (!confirming.value) {
        return;
    }

    router.delete(expenseRoutes.destroy.url(confirming.value.id), {
        preserveScroll: true,
        onFinish: () => (confirming.value = null),
    });
}

function exportCurrent() {
    exportRows('expenses', rows.value, [
        { label: 'Date', value: (row) => row.spent_on },
        { label: 'Category', value: (row) => row.category },
        { label: 'Amount', value: (row) => row.amount },
        { label: 'Currency', value: (row) => row.currency },
        { label: 'Reference', value: (row) => row.reference ?? '' },
        { label: 'Description', value: (row) => row.description ?? '' },
        { label: 'Paid to', value: (row) => row.supplier?.company_name ?? '' },
        { label: 'Recorded by', value: (row) => row.created_by?.name ?? '' },
    ]);
}
</script>

<template>
    <Head title="Expenses" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="Expenses"
            description="What it costs to run the store — rent, wages, utilities. Stock purchases are not expenses here: their cost reaches the profit report as cost of goods sold."
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Button
                    variant="outline"
                    size="dense"
                    as="a"
                    :href="reports.daily.url()"
                >
                    Profit report
                </Button>

                <Button
                    v-if="can('expenses.create')"
                    size="dense"
                    @click="openCreate"
                >
                    <PlusIcon />
                    Record expense
                </Button>
            </template>
        </PageHeader>

        <Deferred data="summary">
            <template #fallback>
                <div class="grid gap-5 sm:grid-cols-3">
                    <div
                        v-for="index in 3"
                        :key="index"
                        class="h-24 animate-pulse rounded-xl bg-muted/40"
                    />
                </div>
            </template>

            <div v-if="summary" class="grid gap-5 sm:grid-cols-3">
                <Card class="p-5">
                    <p class="text-xs text-muted-foreground">Recorded</p>
                    <p class="mt-1 text-2xl font-bold">
                        {{ number(summary.count) }}
                    </p>
                </Card>

                <Card class="p-5">
                    <p class="text-xs text-muted-foreground">Total</p>
                    <p class="mt-1 text-2xl font-bold">{{ money(summary.total) }}</p>
                </Card>

                <Card class="p-5">
                    <p class="mb-2 text-xs text-muted-foreground">
                        Biggest categories
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <Badge
                            v-for="(total, category) in summary.by_category"
                            :key="category"
                            variant="outline"
                            size="sm"
                        >
                            {{ humanize(String(category)) }} {{ money(total) }}
                        </Badge>
                        <span
                            v-if="!Object.keys(summary.by_category).length"
                            class="text-xs text-muted-foreground"
                            >Nothing recorded</span
                        >
                    </div>
                </Card>
            </div>
        </Deferred>

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle>Expense list</CardTitle>
                </template>
            </CardHeader>

            <TableToolbar
                v-model:search="params.search"
                v-model:per-page="params.per_page"
                search-placeholder="Search description, reference, category"
                exportable
                @export="exportCurrent"
                @clear="reset"
            >
                <template #filters>
                    <Select v-model="params.category" class="w-40">
                        <option value="">All categories</option>
                        <option
                            v-for="category in props.categories"
                            :key="category"
                            :value="category"
                        >
                            {{ humanize(category) }}
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
                empty-title="No expenses"
                empty-description="Nothing matches these filters yet."
                @sort="toggleSort"
            >
                <template #cell-spent_on="{ row }">
                    {{ date(row.spent_on) }}
                </template>

                <template #cell-category="{ row }">
                    <Badge variant="outline" size="sm">{{
                        humanize(row.category)
                    }}</Badge>
                </template>

                <template #cell-amount="{ row }">
                    <span class="font-medium">{{
                        money(row.amount, row.currency)
                    }}</span>
                </template>

                <template #cell-reference="{ row }">
                    <span class="font-mono text-2xs text-muted-foreground">{{
                        row.reference ?? '—'
                    }}</span>
                </template>

                <template #cell-description="{ row }">
                    <span class="text-muted-foreground">{{
                        row.description ?? '—'
                    }}</span>
                </template>

                <template #cell-supplier_company_name="{ row }">
                    <span class="text-muted-foreground">{{
                        row.supplier?.company_name ?? '—'
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

                        <DropdownItem
                            v-if="can('expenses.update')"
                            @select="openEdit(row)"
                        >
                            <PencilIcon />
                            Edit
                        </DropdownItem>

                        <DropdownItem
                            v-if="can('expenses.delete')"
                            destructive
                            @select="confirming = row"
                        >
                            <Trash2Icon />
                            Delete
                        </DropdownItem>
                    </Dropdown>
                </template>

                <template v-if="can('expenses.create')" #empty-action>
                    <Button size="dense" @click="openCreate">
                        <PlusIcon />
                        Record expense
                    </Button>
                </template>
            </DataTable>

            <Pagination
                :links="props.expenses.links"
                :from="props.expenses.from"
                :to="props.expenses.to"
                :total="props.expenses.total"
            />
        </Card>

        <Drawer
            :open="Boolean(editing)"
            :title="editing === 'new' ? 'Record expense' : 'Edit expense'"
            description="Dated by the trading day it belongs to, which is the day the profit report counts it against."
            @update:open="editing = null"
        >
            <FormSection title="Expense">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Date" :error="form.errors.spent_on" required>
                        <Input v-model="form.spent_on" type="date" />
                    </FormField>

                    <FormField
                        label="Category"
                        :error="form.errors.category"
                        required
                    >
                        <Select v-model="form.category">
                            <option
                                v-for="category in props.categories"
                                :key="category"
                                :value="category"
                            >
                                {{ humanize(category) }}
                            </option>
                        </Select>
                    </FormField>

                    <FormField label="Amount" :error="form.errors.amount" required>
                        <Input
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                        />
                    </FormField>

                    <FormField label="Currency" :error="form.errors.currency">
                        <Input
                            v-model="form.currency"
                            maxlength="3"
                            class="font-mono"
                        />
                    </FormField>

                    <FormField
                        label="Reference"
                        :error="form.errors.reference"
                        hint="Invoice or receipt number, if there is one."
                    >
                        <Input v-model="form.reference" />
                    </FormField>

                    <FormField
                        label="Paid to"
                        :error="form.errors.supplier_id"
                        hint="Optional — only if the payee is a supplier on file."
                    >
                        <Select v-model="form.supplier_id">
                            <option value="">Not a supplier</option>
                            <option
                                v-for="supplier in props.suppliers"
                                :key="supplier.id"
                                :value="supplier.id"
                            >
                                {{ supplier.company_name }}
                            </option>
                        </Select>
                    </FormField>
                </div>

                <FormField label="Description" :error="form.errors.description">
                    <Textarea v-model="form.description" :rows="3" />
                </FormField>
            </FormSection>

            <template #footer>
                <Button variant="outline" size="dense" @click="editing = null">
                    Cancel
                </Button>
                <Button size="dense" :disabled="form.processing" @click="save">
                    {{ editing === 'new' ? 'Record expense' : 'Save changes' }}
                </Button>
            </template>
        </Drawer>

        <Drawer
            :open="Boolean(confirming)"
            title="Delete expense"
            size="sm"
            @update:open="confirming = null"
        >
            <p class="text-2sm text-muted-foreground">
                Nothing depends on an expense, so it is removed outright. The
                profit report for
                {{ confirming ? date(confirming.spent_on) : 'that day' }} will
                rise by
                {{ confirming ? money(confirming.amount, confirming.currency) : '' }}.
            </p>

            <template #footer>
                <Button variant="outline" size="dense" @click="confirming = null">
                    Cancel
                </Button>
                <Button variant="destructive" size="dense" @click="destroy">
                    Delete expense
                </Button>
            </template>
        </Drawer>
    </AppLayout>
</template>
