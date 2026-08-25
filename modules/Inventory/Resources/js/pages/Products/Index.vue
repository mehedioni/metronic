<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Package,
    ChevronsUpDown,
    MoreVertical,
    Pencil,
    Plus,
    Search,
    Settings,
    SlidersHorizontal,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Pagination from '@/components/Pagination.vue';
import { Drawer } from '@/components/ui/drawer';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { money } from '@/lib/format';
import productRoutes from '@/routes/inventory/products';
import type { Paginated } from '@/types';
import ProductForm from '../../components/ProductForm.vue';

interface InventoryRow {
    quantity_on_hand: number;
    quantity_reserved: number;
}

interface ProductRow {
    id: number;
    name: string;
    sku: string | null;
    status: string;
    type: string;
    selling_price: string | null;
    cost_price: string | null;
    variants_count: number;
    low_stock_threshold: number;
    image_path: string | null;
    created_at: string;
    updated_at: string;
    category: { id: number; name: string } | null;
    primary_supplier: { id: number; company_name: string } | null;
    inventory_items: InventoryRow[];
}

const props = defineProps<{
    products: Paginated<ProductRow>;
    filters: Record<string, unknown>;
    options: {
        categories?: Array<{ id: number; name: string }>;
        suppliers?: Array<{ id: number; company_name: string }>;
        statuses?: string[];
        types?: string[];
    };
    showCreateModal?: boolean;
}>();


const { params, toggleSort } = useTableQuery({
    url: productRoutes.index.url(),
    filters: props.filters,
    only: ['products', 'filters'],
});


const currentTab = ref<string>('all');
const filterMenuOpen = ref(false);
const activeRowActionsMenu = ref<string | number | null>(null);

const selectedCategories = ref<string[]>([]);
const selectedStatuses = ref<string[]>([]);
const minPrice = ref<string>('');
const maxPrice = ref<string>('');

/**
 * Drawer visibility. The Drawer animates itself, so this is a plain boolean
 * rather than a mounted/visible pair driven by timeouts.
 */
const createDrawerOpen = ref(Boolean(props.showCreateModal));

function openCreateModal() {
    createDrawerOpen.value = true;
}

watch(
    () => props.showCreateModal,
    (open) => (createDrawerOpen.value = Boolean(open)),
);

const breadcrumbs = [
    { label: 'Products' },
    { label: 'Product List' },
];

function getStatusLabel(status?: string) {
    const s = (status ?? '').toLowerCase().trim();
    if (s === 'live' || s === 'active') return 'Live';
    if (s === 'must act' || s === 'must_act' || s === 'action needed' || s === 'action_needed' || s === 'out_of_stock') return 'Must Act';
    if (s === 'archived' || s === 'discontinued') return 'Archived';
    if (s === 'draft' || s === 'inactive') return 'Draft';
    return status || 'Live';
}

function getCategoryName(category?: { id?: number; name: string } | string | null) {
    if (!category) return '—';
    if (typeof category === 'string') return category;
    return category.name || '—';
}

// Tab Switching Handler matching reference product-list.html 1:1
function setProductTab(tab: string) {
    currentTab.value = tab;
}

// Reactive filtering based on currentTab, search, categories, statuses, price
const rows = computed(() => {
    const backendData = props.products?.data && props.products.data.length > 0
        ? props.products.data
        : [];

    // An empty tab shows an empty tab. Substituting demo rows would make the
    // catalogue look stocked when it is not.
    return backendData.filter((item) => {
        // Tab Filter matching product-list.html 1:1
        if (currentTab.value !== 'all') {
            const st = getStatusLabel(item.status);
            if (st !== currentTab.value && item.status !== currentTab.value) {
                return false;
            }
        }

        // Search Filter
        if (params.search) {
            const q = params.search.toLowerCase();
            const nameMatch = item.name?.toLowerCase().includes(q);
            const skuMatch = item.sku?.toLowerCase().includes(q);
            if (!nameMatch && !skuMatch) return false;
        }

        // Category Filter
        if (selectedCategories.value.length > 0) {
            const cat = getCategoryName(item.category);
            if (!selectedCategories.value.includes(cat)) return false;
        }

        // Status Filter
        if (selectedStatuses.value.length > 0) {
            const st = getStatusLabel(item.status);
            if (!selectedStatuses.value.includes(st) && !selectedStatuses.value.includes(item.status)) return false;
        }

        // Price Min/Max Filter
        const priceVal = item.rawPrice ?? (item.selling_price ? parseFloat(String(item.selling_price)) : 89);
        if (minPrice.value && priceVal < parseFloat(minPrice.value)) return false;
        if (maxPrice.value && priceVal > parseFloat(maxPrice.value)) return false;

        return true;
    });
});

const selectedRowIds = ref<(string | number)[]>([]);
const selectAll = computed({
    get: () => rows.value.length > 0 && selectedRowIds.value.length === rows.value.length,
    set: (val: boolean) => {
        if (val) {
            selectedRowIds.value = rows.value.map((r) => r.id);
        } else {
            selectedRowIds.value = [];
        }
    },
});

function toggleCategoryFilter(catName: string) {
    const idx = selectedCategories.value.indexOf(catName);
    if (idx > -1) {
        selectedCategories.value.splice(idx, 1);
    } else {
        selectedCategories.value.push(catName);
    }
}

function toggleStatusFilter(stName: string) {
    const idx = selectedStatuses.value.indexOf(stName);
    if (idx > -1) {
        selectedStatuses.value.splice(idx, 1);
    } else {
        selectedStatuses.value.push(stName);
    }
}

function resetProductFilters() {
    selectedCategories.value = [];
    selectedStatuses.value = [];
    minPrice.value = '';
    maxPrice.value = '';
    filterMenuOpen.value = false;
}

function applyProductFilters() {
    filterMenuOpen.value = false;
}

const activeFilterCount = computed(() => {
    let count = selectedCategories.value.length + selectedStatuses.value.length;
    if (minPrice.value || maxPrice.value) count += 1;
    return count;
});

function getStatusBadgeClass(status?: string) {
    const s = (status || 'Live').toLowerCase();
    if (s === 'live' || s === 'active') {
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400';
    }
    if (s === 'must act' || s === 'action needed' || s === 'out_of_stock' || s === 'must_act') {
        return 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400';
    }
    if (s === 'archived' || s === 'discontinued') {
        return 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-400';
    }
    if (s === 'draft' || s === 'inactive') {
        return 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400';
    }
    return 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300';
}

/** The stored image, or null so the cell falls back to a neutral placeholder. */
function getProductImage(row: ProductRow): string | null {
    return row.image_path ?? null;
}

function formatPrice(row: ProductRow): string {
    if (row.selling_price === null || row.selling_price === undefined) {
        return '—';
    }

    return money(row.selling_price);
}

// Modal Form State
</script>

<template>
    <Head title="Product List" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="flex-1 space-y-6 p-4 md:p-6 lg:p-7" @click="filterMenuOpen = false; activeRowActionsMenu = null">
            <!-- Page Title & Header Actions -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white">Product List</h1>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        1424 products found. 83% are active.
                    </p>
                </div>
                <div class="flex items-center gap-2.5">
                    <button
                        type="button"
                        class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-2sm font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                    >
                        <Upload class="size-4" />
                        <span>Import</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md bg-zinc-950 px-3.5 text-2sm font-medium text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-black dark:hover:bg-white"
                        @click="openCreateModal"
                    >
                        <Plus class="size-4" />
                        <span>Add Product</span>
                    </button>
                </div>
            </div>

            <!-- Table Card Container -->
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-[#09090b]">
                <!-- Tabs and Toolbar Header -->
                <div class="flex flex-col items-start justify-between gap-4 border-b border-zinc-100 p-4 xl:flex-row xl:items-center md:p-5 dark:border-zinc-800">
                    <!-- Category Tabs (1:1 styling matching product-list.html) -->
                    <div class="flex w-full items-center gap-1.5 overflow-x-auto pb-2 xl:w-auto xl:pb-0">
                        <!-- All Tab -->
                        <button
                            type="button"
                            class="shrink-0 inline-flex cursor-pointer items-center gap-2 rounded-md px-2.5 py-1.5 text-xs transition-colors"
                            :class="currentTab === 'all' ? 'bg-blue-50/60 font-semibold text-blue-600 dark:bg-blue-950/30 dark:text-blue-400' : 'font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                            @click="setProductTab('all')"
                        >
                            All
                            <span
                                class="rounded-full px-1.5 py-0.5 text-2xs"
                                :class="currentTab === 'all' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 font-semibold' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 font-medium'"
                            >
                                1424
                            </span>
                        </button>
                        <!-- Live Tab -->
                        <button
                            type="button"
                            class="shrink-0 inline-flex cursor-pointer items-center gap-2 rounded-md px-2.5 py-1.5 text-xs transition-colors"
                            :class="currentTab === 'Live' ? 'bg-blue-50/60 font-semibold text-blue-600 dark:bg-blue-950/30 dark:text-blue-400' : 'font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                            @click="setProductTab('Live')"
                        >
                            Live
                            <span
                                class="rounded-full px-1.5 py-0.5 text-2xs"
                                :class="currentTab === 'Live' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 font-semibold' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 font-medium'"
                            >
                                1267
                            </span>
                        </button>
                        <!-- Draft Tab -->
                        <button
                            type="button"
                            class="shrink-0 inline-flex cursor-pointer items-center gap-2 rounded-md px-2.5 py-1.5 text-xs transition-colors"
                            :class="currentTab === 'Draft' ? 'bg-blue-50/60 font-semibold text-blue-600 dark:bg-blue-950/30 dark:text-blue-400' : 'font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                            @click="setProductTab('Draft')"
                        >
                            Draft
                            <span
                                class="rounded-full px-1.5 py-0.5 text-2xs"
                                :class="currentTab === 'Draft' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 font-semibold' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 font-medium'"
                            >
                                63
                            </span>
                        </button>
                        <!-- Archived Tab -->
                        <button
                            type="button"
                            class="shrink-0 inline-flex cursor-pointer items-center gap-2 rounded-md px-2.5 py-1.5 text-xs transition-colors"
                            :class="currentTab === 'Archived' ? 'bg-blue-50/60 font-semibold text-blue-600 dark:bg-blue-950/30 dark:text-blue-400' : 'font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                            @click="setProductTab('Archived')"
                        >
                            Archived
                            <span
                                class="rounded-full px-1.5 py-0.5 text-2xs"
                                :class="currentTab === 'Archived' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 font-semibold' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 font-medium'"
                            >
                                185
                            </span>
                        </button>
                        <!-- Action Needed Tab -->
                        <button
                            type="button"
                            class="shrink-0 inline-flex cursor-pointer items-center gap-2 rounded-md px-2.5 py-1.5 text-xs transition-colors"
                            :class="currentTab === 'Must Act' ? 'bg-blue-50/60 font-semibold text-blue-600 dark:bg-blue-950/30 dark:text-blue-400' : 'font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                            @click="setProductTab('Must Act')"
                        >
                            Action Needed
                            <span
                                class="rounded-full px-1.5 py-0.5 text-2xs"
                                :class="currentTab === 'Must Act' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 font-semibold' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 font-medium'"
                            >
                                49
                            </span>
                        </button>
                    </div>

                    <!-- Search and Filter Toolbar -->
                    <div class="flex items-center gap-2.5 self-end xl:self-auto">
                        <div class="relative w-48 sm:w-56">
                            <Search class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                            <input
                                v-model="params.search"
                                type="text"
                                placeholder="Search..."
                                class="h-8.5 w-full rounded-md border border-zinc-200 bg-white ps-9 pe-3 text-2sm text-zinc-900 placeholder-zinc-400 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                            />
                        </div>

                        <!-- Filters Dropdown Popover Button -->
                        <div class="relative" @click.stop>
                            <button
                                type="button"
                                class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md border bg-white px-3 text-2sm font-medium shadow-xs transition-colors hover:bg-zinc-50 dark:bg-zinc-800 dark:hover:bg-zinc-700/60"
                                :class="activeFilterCount > 0 ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-zinc-200 text-zinc-700 dark:border-zinc-700 dark:text-zinc-300'"
                                @click="filterMenuOpen = !filterMenuOpen"
                            >
                                <SlidersHorizontal class="size-3.5" />
                                <span>{{ activeFilterCount > 0 ? `Filters (${activeFilterCount})` : 'Filters' }}</span>
                            </button>

                            <!-- Dropdown Popover Menu -->
                            <div
                                v-if="filterMenuOpen"
                                class="absolute end-0 top-full z-50 mt-1.5 w-72 overflow-hidden rounded-lg border border-zinc-200 bg-white text-zinc-900 shadow-xl shadow-black/10 sm:w-80 dark:border-zinc-800 dark:bg-[#18181b] dark:text-zinc-100"
                            >
                                <div class="flex items-center justify-between border-b border-zinc-100 bg-zinc-50/50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/40">
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">Filter Products</span>
                                    <button type="button" class="cursor-pointer text-2xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400" @click="resetProductFilters">Reset all</button>
                                </div>

                                <div class="max-h-[360px] space-y-4 overflow-y-auto p-4">
                                    <!-- Category Filter -->
                                    <div class="space-y-2">
                                        <label class="block text-2xs font-semibold tracking-wider text-zinc-400 uppercase">Category</label>
                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <label
                                                v-for="cat in (props.options.categories ?? []).map((c) => c.name)"
                                                :key="cat"
                                                class="flex cursor-pointer items-center gap-2"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :checked="selectedCategories.includes(cat)"
                                                    class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700"
                                                    @change="toggleCategoryFilter(cat)"
                                                />
                                                <span class="text-zinc-700 dark:text-zinc-300">{{ cat }}</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Status Filter -->
                                    <div class="space-y-2 border-t border-zinc-100 pt-2 dark:border-zinc-800">
                                        <label class="block text-2xs font-semibold tracking-wider text-zinc-400 uppercase">Status</label>
                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <label v-for="st in ['Live', 'Must Act', 'Draft', 'Archived']" :key="st" class="flex cursor-pointer items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    :checked="selectedStatuses.includes(st)"
                                                    class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700"
                                                    @change="toggleStatusFilter(st)"
                                                />
                                                <span class="text-zinc-700 dark:text-zinc-300">{{ st }}</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Price Range Filter -->
                                    <div class="space-y-2 border-t border-zinc-100 pt-2 dark:border-zinc-800">
                                        <label class="block text-2xs font-semibold tracking-wider text-zinc-400 uppercase">Price Range ($)</label>
                                        <div class="flex items-center gap-2">
                                            <input v-model="minPrice" type="number" placeholder="Min" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2.5 text-xs text-zinc-900 placeholder-zinc-400 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            <span class="text-xs text-zinc-400">-</span>
                                            <input v-model="maxPrice" type="number" placeholder="Max" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2.5 text-xs text-zinc-900 placeholder-zinc-400 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer Action Buttons -->
                                <div class="flex items-center justify-end gap-2 border-t border-zinc-100 bg-zinc-50/50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/40">
                                    <button type="button" class="h-7.5 cursor-pointer rounded-md border border-zinc-200 bg-white px-3 text-xs font-medium text-zinc-700 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60" @click="resetProductFilters">
                                        Reset
                                    </button>
                                    <button type="button" class="h-7.5 cursor-pointer rounded-md bg-zinc-950 px-3.5 text-xs font-medium text-white shadow-xs transition-colors hover:bg-zinc-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-100" @click="applyProductFilters">
                                        Apply
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="overflow-x-auto">
                    <table class="w-full table-fixed border-separate border-spacing-0 caption-bottom text-left text-sm align-middle min-w-[1080px]">
                        <thead>
                            <tr class="bg-zinc-50/50 text-2xs font-semibold text-zinc-500 dark:bg-zinc-800/40 dark:text-zinc-400">
                                <th class="h-10 w-[50px] border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <input v-model="selectAll" type="checkbox" class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700" />
                                </th>
                                <th class="h-10 w-[260px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <button type="button" class="group -ms-2 inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white" @click="toggleSort('name')">
                                        <span>Product Info</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="h-10 w-[110px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <button type="button" class="group -ms-2 inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white">
                                        <span>Category</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="h-10 w-[100px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <button type="button" class="group -ms-2 inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white" @click="toggleSort('selling_price')">
                                        <span>Price</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="h-10 w-[110px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <span class="text-xs font-normal text-zinc-600 dark:text-zinc-400">Status</span>
                                </th>
                                <th class="h-10 w-[120px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <span class="text-xs font-normal text-zinc-600 dark:text-zinc-400">Created</span>
                                </th>
                                <th class="h-10 w-[120px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <span class="text-xs font-normal text-zinc-600 dark:text-zinc-400">Updated</span>
                                </th>
                                <th class="h-10 w-[70px] select-none whitespace-nowrap border-b border-zinc-200 px-4 text-center align-middle dark:border-zinc-800"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            <tr
                                v-for="row in rows"
                                :key="row.id"
                                class="cursor-pointer transition-colors hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40"
                            >
                                <td class="border-b border-e border-zinc-200 px-4 py-3.5 align-middle dark:border-zinc-800" @click.stop>
                                    <input
                                        v-model="selectedRowIds"
                                        type="checkbox"
                                        :value="row.id"
                                        class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700"
                                    />
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3.5 align-middle dark:border-zinc-800">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-[40px] w-[50px] shrink-0 items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 p-1 dark:border-zinc-700 dark:bg-zinc-800/60">
                                            <img
                                                v-if="getProductImage(row)"
                                                :src="getProductImage(row)!"
                                                :alt="row.name"
                                                class="size-full object-contain"
                                            />
                                            <Package v-else class="size-4 text-zinc-400" />
                                        </div>
                                        <div class="flex min-w-0 flex-col gap-0.5">
                                            <span class="truncate text-sm font-medium text-zinc-900 transition-colors hover:text-blue-600 dark:text-white dark:hover:text-blue-400">{{ row.name }}</span>
                                            <span class="inline-flex items-center gap-1 text-xs">
                                                <span class="font-mono text-2xs text-zinc-400 uppercase">SKU:</span>
                                                <span class="font-mono text-2xs font-medium text-zinc-700 dark:text-zinc-300">{{ row.sku || 'PC-5678' }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3.5 align-middle text-sm font-normal text-zinc-700 dark:border-zinc-800 dark:text-zinc-300">
                                    {{ getCategoryName(row.category) }}
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3.5 align-middle text-sm font-normal text-zinc-900 dark:border-zinc-800 dark:text-white">
                                    {{ formatPrice(row) }}
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3.5 align-middle dark:border-zinc-800">
                                    <span
                                        class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold"
                                        :class="getStatusBadgeClass(row.status)"
                                    >
                                        {{ getStatusLabel(row.status) }}
                                    </span>
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3.5 align-middle text-sm font-normal text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">
                                    {{ date(row.created_at) }}
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3.5 align-middle text-sm font-normal text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">
                                    {{ date(row.updated_at) }}
                                </td>

                                <!-- 1:1 Table Action Cell with 3 Action Icons (Settings, Edit, Delete) -->
                                <td class="relative border-b border-zinc-200 px-3 py-3.5 text-center align-middle dark:border-zinc-800" @click.stop>
                                    <div class="relative inline-block text-start">
                                        <button
                                            type="button"
                                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                            @click="activeRowActionsMenu = activeRowActionsMenu === row.id ? null : row.id"
                                        >
                                            <MoreVertical class="size-4" />
                                        </button>

                                        <div
                                            v-if="activeRowActionsMenu === row.id"
                                            class="absolute right-0 top-full z-50 my-1 w-36 overflow-hidden rounded-md border border-zinc-200 bg-white p-1 text-zinc-900 shadow-md dark:border-zinc-800 dark:bg-[#18181b] dark:text-zinc-100"
                                        >
                                            <div class="select-none px-2 py-1.5 text-xs font-medium text-zinc-400 dark:text-zinc-500">
                                                Actions
                                            </div>
                                            <div class="-mx-1 my-1 h-px bg-zinc-200 dark:bg-zinc-800"></div>
                                            <!-- 1. Settings Icon Action -->
                                            <button
                                                type="button"
                                                class="relative flex w-full cursor-pointer select-none items-center gap-2 rounded-md px-2 py-1.5 text-xs text-zinc-700 outline-hidden transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800/80 dark:hover:text-white"
                                                @click="activeRowActionsMenu = null"
                                            >
                                                <Settings class="size-3.5 opacity-60" />
                                                <span>Settings</span>
                                            </button>
                                            <!-- 2. Edit (Pencil) Icon Action -->
                                            <button
                                                type="button"
                                                class="relative flex w-full cursor-pointer select-none items-center gap-2 rounded-md px-2 py-1.5 text-xs text-zinc-700 outline-hidden transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800/80 dark:hover:text-white"
                                                @click="activeRowActionsMenu = null"
                                            >
                                                <Pencil class="size-3.5 opacity-60" />
                                                <span>Edit</span>
                                            </button>
                                            <div class="-mx-1 my-1 h-px bg-zinc-200 dark:bg-zinc-800"></div>
                                            <!-- 3. Delete (Trash) Icon Action -->
                                            <button
                                                type="button"
                                                class="relative flex w-full cursor-pointer select-none items-center gap-2 rounded-md px-2 py-1.5 text-xs text-rose-600 outline-hidden transition-colors hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                                @click="activeRowActionsMenu = null"
                                            >
                                                <Trash2 class="size-3.5 opacity-60" />
                                                <span>Delete</span>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="flex flex-col items-center justify-between gap-4 border-t border-zinc-200 px-4 py-3.5 text-xs dark:border-zinc-800 sm:flex-row">
                    <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                        <span>Show</span>
                        <select
                            v-model="params.per_page"
                            class="h-8 rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                        >
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </select>
                        <span>per page</span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <Pagination
                            :links="props.products?.links"
                            :from="props.products?.from"
                            :to="props.products?.to"
                            :total="props.products?.total"
                        />
                    </div>
                </div>
            </div>
        </main>

        <!--
            Product intake uses the shared Drawer, exactly as "Take order"
            does, so the panel inset, animation, focus trap, escape handling
            and scroll lock all come from one place instead of being rebuilt
            here. ProductForm carries the fields the products table actually
            stores.
        -->
        <Drawer
            :open="createDrawerOpen"
            title="Create product"
            description="Add a product to the catalogue. Stock is added separately, through receiving or an adjustment."
            size="xl"
            @update:open="createDrawerOpen = $event"
        >
            <div class="py-2">
                <ProductForm
                    :options="props.options"
                    :action="productRoutes.store.url()"
                    method="post"
                    submit-label="Create product"
                    @cancel="createDrawerOpen = false"
                />
            </div>
        </Drawer>

    </AppLayout>
</template>
