<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { PencilIcon, PlusIcon, Trash2Icon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TableToolbar from '@/components/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Drawer } from '@/components/ui/drawer';
import { Dropdown, DropdownItem } from '@/components/ui/dropdown';
import { Select } from '@/components/ui/select';
import { useCsvExport } from '@/composables/useCsvExport';
import { usePageErrors } from '@/composables/usePageErrors';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { number } from '@/lib/format';
import categoryRoutes from '@/routes/inventory/categories';
import type { Paginated } from '@/types';

interface CategoryRow {
    id: string;
    name: string;
    slug: string;
    status: string;
    products_count: number;
    parent: { id: string; name: string } | null;
}

const props = defineProps<{
    categories: Paginated<CategoryRow>;
    filters: Record<string, unknown>;
    statuses: string[];
    parents: Array<{ id: string; name: string }>;
}>();

const { can } = usePermissions();
const { firstOf } = usePageErrors();
const { exportRows } = useCsvExport();

const { params, loading, toggleSort, sortState, reset } = useTableQuery({
    url: categoryRoutes.index.url(),
    filters: props.filters,
    only: ['categories', 'filters'],
});

const rows = computed(() => props.categories.data);
const confirming = ref<CategoryRow | null>(null);

const columns: Column[] = [
    { key: 'name', label: 'Category', sort: 'name', width: '260px' },
    { key: 'parent.name', label: 'Parent', width: '180px' },
    {
        key: 'products_count',
        label: 'Products',
        sort: 'products_count',
        align: 'center',
        width: '110px',
    },
    { key: 'status', label: 'Status', sort: 'status', width: '120px' },
];

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Categories' },
    { label: 'Category List' },
];

function destroy() {
    if (!confirming.value) {
        return;
    }

    router.delete(categoryRoutes.destroy.url(confirming.value.id), {
        preserveScroll: true,
        onFinish: () => (confirming.value = null),
    });
}

function exportCurrent() {
    exportRows('categories', rows.value, [
        { label: 'Name', value: (row) => row.name },
        { label: 'Slug', value: (row) => row.slug },
        { label: 'Parent', value: (row) => row.parent?.name ?? '' },
        { label: 'Products', value: (row) => row.products_count },
        { label: 'Status', value: (row) => row.status },
    ]);
}
</script>

<template>
    <Head title="Categories" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="Categories"
            :description="`${number(props.categories.total)} categories`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Button
                    v-if="can('categories.create')"
                    size="dense"
                    as="a"
                    :href="categoryRoutes.create.url()"
                >
                    <PlusIcon />
                    New category
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle>Category list</CardTitle>
                </template>
            </CardHeader>

            <TableToolbar
                v-model:search="params.search"
                v-model:per-page="params.per_page"
                search-placeholder="Search category"
                exportable
                @export="exportCurrent"
                @clear="reset"
            >
                <template #filters>
                    <Select v-model="params.parent_id" class="w-44">
                        <option value="">All parents</option>
                        <option
                            v-for="parent in props.parents"
                            :key="parent.id"
                            :value="parent.id"
                        >
                            {{ parent.name }}
                        </option>
                    </Select>

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
                empty-title="No categories"
                empty-description="Nothing matches these filters yet."
                @sort="toggleSort"
            >
                <template #cell-name="{ row }">
                    <Link
                        :href="categoryRoutes.show.url(row.id)"
                        class="font-medium hover:underline"
                        >{{ row.name }}</Link
                    >
                    <span class="block font-mono text-[11px] text-muted-foreground">
                        {{ row.slug }}
                    </span>
                </template>

                <template #cell-parent_name="{ row }">
                    <span class="text-muted-foreground">
                        {{ row.parent?.name ?? 'Top level' }}
                    </span>
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
                                :href="categoryRoutes.show.url(row.id)"
                                class="flex w-full items-center gap-2"
                                >View</Link
                            >
                        </DropdownItem>

                        <DropdownItem v-if="can('categories.update')" as-child>
                            <Link
                                :href="categoryRoutes.edit.url(row.id)"
                                class="flex w-full items-center gap-2"
                            >
                                <PencilIcon />
                                Edit
                            </Link>
                        </DropdownItem>

                        <DropdownItem
                            v-if="can('categories.delete')"
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
                :links="props.categories.links"
                :from="props.categories.from"
                :to="props.categories.to"
                :total="props.categories.total"
            />
        </Card>

        <Drawer
            :open="Boolean(confirming)"
            title="Delete category"
            size="sm"
            @update:open="confirming = null"
        >
            <p class="text-[0.8125rem] text-muted-foreground">
                A category holding products, or with child categories, cannot be
                deleted — the backend refuses it and says which rule applied.
            </p>

            <p v-if="firstOf('inventory')" class="mt-3 text-[11px] text-danger">
                {{ firstOf('inventory') }}
            </p>

            <template #footer>
                <Button variant="outline" size="dense" @click="confirming = null">
                    Cancel
                </Button>
                <Button variant="destructive" size="dense" @click="destroy">
                    Delete category
                </Button>
            </template>
        </Drawer>
    </AppLayout>
</template>
