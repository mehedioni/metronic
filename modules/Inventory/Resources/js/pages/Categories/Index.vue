<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Activity,
    ChevronsUpDown,
    Compass,
    Eye,
    Footprints,
    Image as ImageIcon,
    Layers,
    Mountain,
    Package,
    Plus,
    Search,
    Shapes,
    Shield,
    Sparkles,
    SquarePen,
    Star,
    Trash,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Pagination from '@/components/Pagination.vue';
import { Drawer } from '@/components/ui/drawer';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { money, number } from '@/lib/format';
import categoryRoutes from '@/routes/inventory/categories';
import type { Paginated } from '@/types';

interface CategoryRow {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    status: string;
    products_count: number;
    parent: { id: number; name: string } | null;
}

const props = defineProps<{
    categories: Paginated<CategoryRow>;
    filters: Record<string, unknown>;
    statuses: string[];
    parents: Array<{ id: number; name: string }>;
    openCreateModal?: boolean;
    initialViewingCategory?: CategoryRow | null;
}>();

const { params, toggleSort, reset } = useTableQuery({
    url: categoryRoutes.index.url(),
    filters: props.filters,
    only: ['categories', 'filters'],
});

const rows = computed(() => props.categories.data);
const confirmingDelete = ref<CategoryRow | null>(null);

const createDrawerOpen = ref(props.openCreateModal ?? false);
const viewingCategory = ref<CategoryRow | null>(props.initialViewingCategory ?? null);
const editingCategory = ref<CategoryRow | null>(null);

const form = useForm({
    name: '',
    slug: '',
    description: '',
    parent_id: '',
    status: 'active',
});

const detailsForm = useForm({
    name: '',
    slug: '',
    description: '',
    status: 'active',
});

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Categories' },
    { label: 'Category List' },
];

function getCategoryIcon(name: string, idx: number) {
    const icons = [Footprints, Package, Zap, Mountain, Sparkles, Shield, Compass, Activity, Shapes, Layers];
    return icons[idx % icons.length];
}

function getCategoryCode(id: number, slug: string) {
    const prefix = slug ? slug.substring(0, 2).toUpperCase() : 'WM';
    const num = 8400 + id * 17;
    return `${prefix}-${num}`;
}

function getCategoryEarnings(count: number, id: number) {
    const amount = (count * 150 + id * 382.5) % 120000 + 498.75;
    return money(amount);
}

function openCreateModal() {
    form.reset();
    form.clearErrors();
    editingCategory.value = null;
    viewingCategory.value = null;
    createDrawerOpen.value = true;
}

function openEditModal(cat: CategoryRow) {
    editingCategory.value = cat;
    viewingCategory.value = null;
    form.name = cat.name;
    form.slug = cat.slug;
    form.description = cat.description ?? '';
    form.parent_id = cat.parent ? String(cat.parent.id) : '';
    form.status = (cat.status ?? 'active').toLowerCase();
    form.clearErrors();
    createDrawerOpen.value = true;
}

function openDetailsModal(cat: CategoryRow) {
    createDrawerOpen.value = false;
    viewingCategory.value = cat;
    detailsForm.name = cat.name;
    detailsForm.slug = cat.slug;
    detailsForm.description = cat.description ?? '';
    detailsForm.status = (cat.status ?? 'active').toLowerCase();
}

function closeCreateModal() {
    createDrawerOpen.value = false;
    editingCategory.value = null;
    form.reset();
}

function closeDetailsModal() {
    viewingCategory.value = null;
}

function submitCategory() {
    if (editingCategory.value) {
        form.put(categoryRoutes.update.url(editingCategory.value.id), {
            preserveScroll: true,
            onSuccess: () => closeCreateModal(),
        });
    } else {
        form.post(categoryRoutes.store.url(), {
            preserveScroll: true,
            onSuccess: () => closeCreateModal(),
        });
    }
}

function saveDetailsCategory() {
    if (!viewingCategory.value) return;
    detailsForm.put(categoryRoutes.update.url(viewingCategory.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDetailsModal(),
    });
}

function destroy() {
    if (!confirmingDelete.value) {
        return;
    }

    router.delete(categoryRoutes.destroy.url(confirmingDelete.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmingDelete.value = null),
    });
}

// Checkbox selection
const selectedRows = ref<number[]>([]);
const selectAll = ref(false);

function toggleSelectAll() {
    if (selectAll.value) {
        selectedRows.value = rows.value.map((r) => r.id);
    } else {
        selectedRows.value = [];
    }
}

function toggleRowSelect(id: number) {
    const idx = selectedRows.value.indexOf(id);
    if (idx > -1) {
        selectedRows.value.splice(idx, 1);
    } else {
        selectedRows.value.push(id);
    }
}

// Featured state
const featuredMap = ref<Record<number, boolean>>({});
function isFeatured(id: number, idx: number) {
    if (featuredMap.value[id] !== undefined) {
        return featuredMap.value[id];
    }
    return idx % 2 === 0;
}
function toggleFeatured(id: number, idx: number) {
    featuredMap.value[id] = !isFeatured(id, idx);
}
</script>

<template>
    <Head title="Category List" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="flex-1 space-y-6 px-4 py-6 lg:px-8">
            <!-- Header Page Title & Action -->
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-0.5">
                    <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Category List</h1>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ number(props.categories.total) }} categories found. 12% needs your attention.
                    </span>
                </div>
                <button
                    type="button"
                    class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md bg-zinc-950 px-3 text-[0.8125rem] font-medium text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-zinc-950 dark:hover:bg-white"
                    @click="openCreateModal"
                >
                    <Plus class="size-4" />
                    <span>Add Category</span>
                </button>
            </div>

            <!-- Category Table Card (1:1 Metronic Vite Concept UI) -->
            <div class="rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-[#121215]">
                <!-- Card Header / Toolbar -->
                <div class="flex min-h-14 flex-wrap items-center justify-between gap-2.5 border-b border-zinc-200 px-5 py-3.5 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <div class="relative w-56">
                            <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />
                            <input
                                id="category-search-input"
                                v-model="params.search"
                                type="text"
                                placeholder="Search..."
                                class="h-8.5 w-full rounded-md border border-zinc-200 bg-white ps-9 pe-3 text-xs text-zinc-900 placeholder-zinc-400 shadow-xs focus:ring-1 focus:ring-zinc-400 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                            />
                        </div>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="min-h-[380px] overflow-x-auto">
                    <table class="w-full table-fixed border-separate border-spacing-0 text-left align-middle text-sm">
                        <thead>
                            <tr class="bg-zinc-50/50 text-[11px] font-semibold text-zinc-500 dark:bg-zinc-800/40 dark:text-zinc-400">
                                <th class="h-10 w-[50px] border-e border-b border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <input
                                        v-model="selectAll"
                                        type="checkbox"
                                        class="size-4.5 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700"
                                        @change="toggleSelectAll"
                                    />
                                </th>
                                <th class="h-10 w-[300px] border-e border-b border-zinc-200 px-4 text-start align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                    <button
                                        type="button"
                                        class="-ms-2 inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                                        @click="toggleSort('name')"
                                    >
                                        <span>Category</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="h-10 w-[140px] border-e border-b border-zinc-200 px-4 text-start align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                    <button
                                        type="button"
                                        class="-ms-2 inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                                        @click="toggleSort('products_count')"
                                    >
                                        <span>Products QTY</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="h-10 w-[160px] border-e border-b border-zinc-200 px-4 text-start align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                    <button
                                        type="button"
                                        class="-ms-2 inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                                    >
                                        <span>Total Earnings</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="h-10 w-[120px] border-e border-b border-zinc-200 px-4 text-start align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                    <button
                                        type="button"
                                        class="-ms-2 inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                                        @click="toggleSort('status')"
                                    >
                                        <span>Status</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="h-10 w-[110px] border-e border-b border-zinc-200 px-4 text-center align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                    <span class="text-xs font-normal text-zinc-600 dark:text-zinc-400">Featured</span>
                                </th>
                                <th class="h-10 w-[110px] border-b border-zinc-200 px-4 text-center align-middle dark:border-zinc-800"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            <tr v-if="rows.length === 0">
                                <td colspan="7" class="border-b border-zinc-200 py-12 text-center text-sm text-zinc-400 dark:border-zinc-800">
                                    No categories found.
                                </td>
                            </tr>
                            <tr
                                v-for="(row, idx) in rows"
                                :key="row.id"
                                class="transition-colors hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40"
                            >
                                <!-- Checkbox -->
                                <td class="border-e border-b border-zinc-200 px-4 py-3 align-middle dark:border-zinc-800">
                                    <input
                                        type="checkbox"
                                        :checked="selectedRows.includes(row.id)"
                                        class="size-4.5 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700"
                                        @change="toggleRowSelect(row.id)"
                                    />
                                </td>

                                <!-- Category Info -->
                                <td class="border-e border-b border-zinc-200 px-4 py-3 align-middle dark:border-zinc-800">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-[40px] w-[50px] shrink-0 items-center justify-center rounded-md border border-zinc-200 bg-zinc-50/80 p-1 dark:border-zinc-700 dark:bg-zinc-800/60">
                                            <component :is="getCategoryIcon(row.name, idx)" class="size-5 text-zinc-700 dark:text-zinc-300" />
                                        </div>
                                        <div class="flex flex-col gap-0.5">
                                            <button
                                                type="button"
                                                class="text-left text-sm font-medium tracking-tight text-zinc-900 transition-colors hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                                @click="openDetailsModal(row)"
                                            >
                                                {{ row.name }}
                                            </button>
                                            <span class="text-xs text-zinc-400">
                                                Category ID: <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ getCategoryCode(row.id, row.slug) }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Products QTY -->
                                <td class="border-e border-b border-zinc-200 px-4 py-3 text-start align-middle text-sm font-normal text-zinc-900 dark:border-zinc-800 dark:text-white">
                                    {{ row.products_count }}
                                </td>

                                <!-- Total Earnings -->
                                <td class="border-e border-b border-zinc-200 px-4 py-3 text-start align-middle text-sm font-normal text-zinc-900 dark:border-zinc-800 dark:text-white">
                                    {{ getCategoryEarnings(row.products_count, row.id) }}
                                </td>

                                <!-- Status Badge -->
                                <td class="border-e border-b border-zinc-200 px-4 py-3 text-start align-middle dark:border-zinc-800">
                                    <span
                                        class="inline-flex h-6 min-w-6 items-center justify-center rounded-md px-[0.45rem] text-xs font-medium"
                                        :class="
                                            row.status === 'active' || row.status === 'Active'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400'
                                                : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-400'
                                        "
                                    >
                                        {{ row.status === 'active' || row.status === 'Active' ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>

                                <!-- Featured Checkbox -->
                                <td class="border-e border-b border-zinc-200 px-4 py-3 text-center align-middle dark:border-zinc-800">
                                    <div class="flex justify-center">
                                        <input
                                            type="checkbox"
                                            :checked="isFeatured(row.id, idx)"
                                            class="size-4.5 cursor-pointer rounded border-zinc-300 text-blue-600 focus:ring-0 dark:border-zinc-700"
                                            @change="toggleFeatured(row.id, idx)"
                                        />
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="border-b border-zinc-200 px-4 py-3 text-center align-middle dark:border-zinc-800">
                                    <div class="flex items-center justify-center gap-1">
                                        <button
                                            type="button"
                                            class="flex size-7 cursor-pointer items-center justify-center rounded-md text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-white"
                                            title="View category"
                                            @click="openDetailsModal(row)"
                                        >
                                            <Eye class="size-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            class="flex size-7 cursor-pointer items-center justify-center rounded-md text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-white"
                                            title="Edit category"
                                            @click="openEditModal(row)"
                                        >
                                            <SquarePen class="size-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            class="flex size-7 cursor-pointer items-center justify-center rounded-md text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-rose-600 dark:hover:bg-zinc-800 dark:hover:text-rose-400"
                                            title="Delete category"
                                            @click="confirmingDelete = row"
                                        >
                                            <Trash class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer / Pagination -->
                <div class="flex min-h-[56px] items-center border-t border-zinc-200 px-5 dark:border-zinc-800">
                    <div class="flex grow flex-col flex-wrap items-center justify-between gap-2.5 py-2.5 sm:flex-row sm:py-0">
                        <!-- Rows per page -->
                        <div class="order-2 flex flex-wrap items-center space-x-2.5 pb-2.5 sm:order-1 sm:pb-0">
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">Rows per page</div>
                            <select
                                v-model.number="params.per_page"
                                class="h-7 w-20 cursor-pointer rounded-md border border-zinc-200 bg-white px-2 text-xs font-medium text-zinc-700 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                            >
                                <option :value="10">10</option>
                                <option :value="15">15</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                            </select>
                        </div>

                        <!-- Page navigation -->
                        <div class="order-1 flex flex-col items-center justify-center gap-2.5 pt-2.5 sm:order-2 sm:flex-row sm:justify-end sm:pt-0">
                            <Pagination
                                :links="props.categories.links"
                                :from="props.categories.from"
                                :to="props.categories.to"
                                :total="props.categories.total"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- CATEGORY DETAILS MODAL DRAWER (1080px Large Modal 1:1 matching reference category-details.html) -->
        <div
            v-if="viewingCategory"
            id="category-details-backdrop"
            class="fixed inset-0 z-40 bg-black/40 backdrop-blur-xs transition-opacity duration-300"
            @click="closeDetailsModal"
        />

        <div
            v-if="viewingCategory"
            id="category-details-modal"
            class="fixed inset-5 start-auto z-50 flex h-auto max-w-[calc(100vw-40px)] w-full flex-col overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-2xl transition-all duration-300 ease-in-out dark:border-zinc-800 dark:bg-[#121215] lg:w-[1080px]"
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                <h2 class="text-base font-medium text-zinc-900 dark:text-white">Category Details</h2>
                <button
                    type="button"
                    class="cursor-pointer rounded-sm p-1 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                    @click="closeDetailsModal"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Category Sub-Header Banner -->
            <div class="flex flex-col flex-wrap justify-between gap-3 border-b border-zinc-200 bg-zinc-50/40 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900/30 sm:flex-row sm:items-center">
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center gap-2.5">
                        <span class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ viewingCategory.name }}</span>
                        <span
                            class="inline-flex items-center rounded-sm border px-2 py-0.5 text-[11px] font-medium"
                            :class="
                                viewingCategory.status === 'active' || viewingCategory.status === 'Active'
                                    ? 'border-emerald-200/60 bg-emerald-50 text-emerald-700 dark:border-emerald-800/60 dark:bg-emerald-950/60 dark:text-emerald-400'
                                    : 'border-rose-200/60 bg-rose-50 text-rose-700 dark:border-rose-800/60 dark:bg-rose-950/60 dark:text-rose-400'
                            "
                        >
                            {{ viewingCategory.status === 'active' || viewingCategory.status === 'Active' ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                        <span>{{ viewingCategory.name }}</span>
                        <span class="font-medium text-zinc-800 dark:text-zinc-300">{{ getCategoryCode(viewingCategory.id, viewingCategory.slug) }}</span>
                        <span class="size-1 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                        <span>Created <strong class="font-medium text-zinc-800 dark:text-zinc-300">16 Jan, 2025</strong></span>
                        <span class="size-1 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                        <span>Last Updated <strong class="font-medium text-zinc-800 dark:text-zinc-300">2 days ago</strong></span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="cursor-pointer rounded-md border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        @click="closeDetailsModal"
                    >
                        Close
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-md border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        @click="confirmingDelete = viewingCategory; closeDetailsModal()"
                    >
                        Delete
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-md bg-zinc-950 px-3.5 py-1.5 text-xs font-medium text-white shadow-xs transition-colors hover:bg-zinc-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-100"
                        @click="saveDetailsCategory"
                    >
                        Save
                    </button>
                </div>
            </div>

            <!-- Modal Body Content -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                <div class="grid grid-cols-1 gap-6 items-start lg:grid-cols-12">
                    <!-- Left 7 Columns -->
                    <div class="space-y-6 lg:col-span-7">
                        <!-- Metrics Card -->
                        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-[#151518]">
                            <div class="mb-3 text-xs font-medium text-zinc-500">Metrics</div>
                            <div class="grid grid-cols-5 divide-x divide-zinc-100 text-center dark:divide-zinc-800/80">
                                <div class="px-1">
                                    <div class="text-[11px] text-zinc-400">Total Qty</div>
                                    <div class="mt-1 text-sm font-bold text-zinc-900 dark:text-white">{{ viewingCategory.products_count || 78 }}</div>
                                </div>
                                <div class="px-1">
                                    <div class="text-[11px] text-zinc-400">Earning</div>
                                    <div class="mt-1 text-sm font-bold text-zinc-900 dark:text-white">{{ getCategoryEarnings(viewingCategory.products_count, viewingCategory.id) }}</div>
                                </div>
                                <div class="px-1">
                                    <div class="text-[11px] text-zinc-400">Return Rate</div>
                                    <div class="mt-1 text-sm font-bold text-emerald-600 dark:text-emerald-400">+1.3%</div>
                                </div>
                                <div class="px-1">
                                    <div class="text-[11px] text-zinc-400">Avg. Margin</div>
                                    <div class="mt-1 text-sm font-bold text-zinc-900 dark:text-white">38%</div>
                                </div>
                                <div class="px-1">
                                    <div class="text-[11px] text-zinc-400">Avg. Rating</div>
                                    <div class="mt-1 inline-flex items-center gap-1 rounded bg-amber-50 px-1.5 py-0.5 text-xs font-bold text-amber-600 dark:bg-amber-950/40">
                                        <Star class="size-3 fill-amber-500 text-amber-500" />
                                        <span>5.0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Analytics Card -->
                        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-[#151518]">
                            <div class="mb-3 text-xs font-medium text-zinc-500">Analytics</div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="space-y-2 rounded-lg border border-zinc-200/70 bg-zinc-50/70 p-3 dark:border-zinc-800 dark:bg-zinc-900/40">
                                    <div class="text-xs text-zinc-500">Avg. Product Price</div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg font-bold text-zinc-900 dark:text-white">$96.23</span>
                                        <span class="inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">↗ 3.5%</span>
                                    </div>
                                    <div class="h-12 w-full pt-2">
                                        <svg viewBox="0 0 200 45" class="h-full w-full fill-none stroke-blue-500" stroke-width="2">
                                            <path d="M0,35 C30,32 50,22 80,25 C110,28 140,15 170,18 C185,19 195,8 200,6" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="space-y-2 rounded-lg border border-zinc-200/70 bg-zinc-50/70 p-3 dark:border-zinc-800 dark:bg-zinc-900/40">
                                    <div class="text-xs text-zinc-500">Category Product Sales</div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg font-bold text-zinc-900 dark:text-white">12,346</span>
                                        <span class="inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">↗ 4%</span>
                                        <span class="ms-auto text-xs text-zinc-400">$243,784.02</span>
                                    </div>
                                    <div class="h-12 w-full pt-2">
                                        <svg viewBox="0 0 200 45" class="h-full w-full fill-none stroke-indigo-500" stroke-width="2">
                                            <path d="M0,38 C25,12 45,15 70,25 C95,35 125,28 150,20 C175,12 190,14 200,10" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Category Items Table -->
                        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-[#151518]">
                            <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-800">
                                <span class="text-xs font-medium text-zinc-900 dark:text-white">Category Items</span>
                                <button type="button" class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">Select All</button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs text-zinc-600 dark:text-zinc-400">
                                    <thead class="border-b border-zinc-200 bg-zinc-50/50 text-[11px] font-medium text-zinc-400 dark:border-zinc-800 dark:bg-zinc-900/40">
                                        <tr>
                                            <th class="px-4 py-2.5">Product Info</th>
                                            <th class="px-4 py-2.5">Total Sales</th>
                                            <th class="px-4 py-2.5 text-end">Last Moved</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                                            <td class="px-4 py-2.5">
                                                <div class="font-medium text-zinc-900 dark:text-white">Air Max 270 React Eng...</div>
                                                <div class="text-[10px] text-zinc-400">sku: WM-8421</div>
                                            </td>
                                            <td class="px-4 py-2.5 font-medium text-zinc-800 dark:text-zinc-200">$4,283.00</td>
                                            <td class="px-4 py-2.5 text-end text-zinc-500">18 Aug, 2025</td>
                                        </tr>
                                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                                            <td class="px-4 py-2.5">
                                                <div class="font-medium text-zinc-900 dark:text-white">Trail Runner Z2</div>
                                                <div class="text-[10px] text-zinc-400">sku: UC-3990</div>
                                            </td>
                                            <td class="px-4 py-2.5 font-medium text-zinc-800 dark:text-zinc-200">$923.00</td>
                                            <td class="px-4 py-2.5 text-end text-zinc-500">17 Aug, 2025</td>
                                        </tr>
                                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                                            <td class="px-4 py-2.5">
                                                <div class="font-medium text-zinc-900 dark:text-white">Urban Flex Knit Low...</div>
                                                <div class="text-[10px] text-zinc-400">sku: KB-8820</div>
                                            </td>
                                            <td class="px-4 py-2.5 font-medium text-zinc-800 dark:text-zinc-200">$1,097.50</td>
                                            <td class="px-4 py-2.5 text-end text-zinc-500">15 Aug, 2025</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Right 5 Columns -->
                    <div class="space-y-4 lg:col-span-5">
                        <div class="relative flex min-h-[220px] flex-col items-center justify-center rounded-lg border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-800 dark:bg-zinc-900/30">
                            <div class="flex size-28 items-center justify-center text-zinc-700 dark:text-zinc-300">
                                <ImageIcon class="size-16 text-zinc-400" />
                            </div>
                            <button type="button" class="absolute right-3 bottom-3 rounded-md border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                Change
                            </button>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Category Name</label>
                            <input
                                v-model="detailsForm.name"
                                type="text"
                                class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-xs text-zinc-900 focus:ring-1 focus:ring-zinc-400 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Status</label>
                            <select
                                v-model="detailsForm.status"
                                class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-xs text-zinc-700 focus:ring-1 focus:ring-zinc-400 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Description</label>
                            <textarea
                                v-model="detailsForm.description"
                                rows="4"
                                class="w-full rounded-md border border-zinc-200 bg-white p-3 text-xs text-zinc-700 focus:ring-1 focus:ring-zinc-400 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300"
                            />
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <input
                                id="details-featured-checkbox"
                                type="checkbox"
                                class="size-4 cursor-pointer rounded border-zinc-300 text-blue-600 focus:ring-0 dark:border-zinc-700"
                            />
                            <label for="details-featured-checkbox" class="cursor-pointer text-xs font-medium text-zinc-700 dark:text-zinc-300">Featured</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ADD / EDIT CATEGORY MODAL DRAWER (500px Sheet Modal 1:1 matching reference create-category.html) -->
        <div
            v-if="createDrawerOpen"
            id="create-category-backdrop"
            class="fixed inset-0 z-40 bg-black/40 backdrop-blur-xs transition-opacity duration-300"
            @click="closeCreateModal"
        />

        <div
            id="create-category-modal"
            class="fixed inset-5 start-auto z-50 flex h-auto max-w-[calc(100vw-40px)] w-full flex-col overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-2xl transition-all duration-300 ease-in-out dark:border-zinc-800 dark:bg-[#121215] lg:w-[500px]"
            :class="createDrawerOpen ? 'translate-x-0' : 'translate-x-full pointer-events-none opacity-0'"
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                <h2 class="text-base font-medium text-zinc-900 dark:text-white">
                    {{ editingCategory ? 'Edit Category' : 'Add Category' }}
                </h2>
                <button
                    type="button"
                    class="cursor-pointer rounded-sm p-1 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                    @click="closeCreateModal"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Modal Body -->
            <form class="flex flex-1 flex-col overflow-hidden" @submit.prevent="submitCategory">
                <div class="flex-1 space-y-5 overflow-y-auto p-6">
                    <!-- Upload Box -->
                    <div class="relative flex h-[200px] w-full items-center justify-center rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div class="flex flex-col items-center justify-center gap-2">
                            <ImageIcon class="size-9 text-zinc-400" />
                        </div>
                        <input id="category-image-upload" type="file" class="hidden" accept="image/*" />
                        <label for="category-image-upload" class="absolute right-3 bottom-3 cursor-pointer">
                            <span class="inline-flex items-center gap-1 rounded-md border border-zinc-200 bg-white px-3 py-1 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">
                                Upload
                            </span>
                        </label>
                    </div>

                    <!-- Category Name -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Category Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="Category Name"
                            class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-xs text-zinc-900 focus:ring-1 focus:ring-zinc-400 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"
                            required
                        />
                        <p v-if="form.errors.name" class="text-[11px] text-rose-500">{{ form.errors.name }}</p>
                    </div>

                    <!-- Status -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Status</label>
                        <select
                            v-model="form.status"
                            class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-xs text-zinc-700 focus:ring-1 focus:ring-zinc-400 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300"
                        >
                            <option value="" disabled>Select Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <!-- Description -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="4"
                            placeholder="Category Description"
                            class="w-full rounded-md border border-zinc-200 bg-white p-3 text-xs text-zinc-700 focus:ring-1 focus:ring-zinc-400 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300"
                        />
                    </div>

                    <!-- Featured Checkbox -->
                    <div class="flex items-center gap-2 pt-1">
                        <input
                            id="featured-checkbox"
                            type="checkbox"
                            class="size-4 cursor-pointer rounded border-zinc-300 text-blue-600 focus:ring-0 dark:border-zinc-700"
                        />
                        <label for="featured-checkbox" class="cursor-pointer text-xs font-medium text-zinc-700 dark:text-zinc-300">Featured</label>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex shrink-0 items-center justify-end gap-3 border-t border-zinc-200 bg-zinc-50/40 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900/30">
                    <button
                        type="button"
                        class="cursor-pointer rounded-md px-3.5 py-1.5 text-xs font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        @click="closeCreateModal"
                    >
                        Close
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="cursor-pointer rounded-md bg-zinc-950 px-4 py-1.5 text-xs font-medium text-white shadow-xs transition-colors hover:bg-zinc-800 disabled:opacity-50 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-100"
                    >
                        {{ editingCategory ? 'Save Changes' : 'Create' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Delete Confirmation Drawer -->
        <Drawer
            :open="Boolean(confirmingDelete)"
            title="Delete Category"
            size="sm"
            @update:open="confirmingDelete = null"
        >
            <p class="text-xs text-zinc-600 dark:text-zinc-400">
                Are you sure you want to delete category "<span class="font-semibold text-zinc-900 dark:text-white">{{ confirmingDelete?.name }}</span>"? Categories with products or child categories cannot be deleted.
            </p>

            <template #footer>
                <button
                    type="button"
                    class="cursor-pointer rounded-md border border-zinc-200 bg-white px-3.5 py-1.5 text-xs font-medium text-zinc-700 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                    @click="confirmingDelete = null"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-md bg-rose-600 px-3.5 py-1.5 text-xs font-medium text-white shadow-xs transition-colors hover:bg-rose-700"
                    @click="destroy"
                >
                    Delete Category
                </button>
            </template>
        </Drawer>
    </AppLayout>
</template>
