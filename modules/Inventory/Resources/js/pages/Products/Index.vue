<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Package,
    ChevronsUpDown,
    MoreVertical,
    Pencil,
    Plus,
    Search,
    SlidersHorizontal,
    Trash2,
    Upload,
    Eye,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Pagination from '@/components/Pagination.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Drawer } from '@/components/ui/drawer';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, money } from '@/lib/format';
import productRoutes from '@/routes/inventory/products';
import type { Paginated } from '@/types';
import ProductForm from '../../components/ProductForm.vue';
import type { ProductImage } from '../../components/ProductImageManager.vue';

interface InventoryRow {
    quantity_on_hand: number;
    quantity_reserved: number;
}

interface ProductVariantRow {
    id: number;
    sku: string | null;
    name: string;
    cost_price: string | null;
    selling_price: string | null;
    status: string;
    low_stock_threshold?: number;
}

interface ProductRow {
    id: number;
    uuid?: string;
    name: string;
    sku: string | null;
    description?: string | null;
    status: string;
    type: string;
    category_id?: number | string | null;
    primary_supplier_id?: number | string | null;
    selling_price: string | null;
    cost_price: string | null;
    variants_count: number;
    low_stock_threshold: number;
    /** Serialised by the ProductImage model, URL included. */
    primary_image: { id: number; url: string | null } | null;
    created_at: string;
    updated_at: string;
    category: { id: number; name: string } | null;
    primary_supplier: { id: number; company_name: string } | null;
    inventory_items: InventoryRow[];
    variants?: ProductVariantRow[];
    suppliers?: Array<{ id: number; company_name: string }>;
    images?: ProductImage[];
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
    counts?: {
        all: number;
        active: number;
        inactive: number;
        archived: number;
    };
    showCreateModal?: boolean;
}>();

const tabCounts = computed(() => {
    return {
        all: props.counts?.all ?? props.products?.total ?? 0,
        active: props.counts?.active ?? 0,
        inactive: props.counts?.inactive ?? 0,
        archived: props.counts?.archived ?? 0,
    };
});

const { params, toggleSort } = useTableQuery({
    url: productRoutes.index.url(),
    filters: props.filters,
    only: ['products', 'filters'],
});

const currentTab = computed({
    get: () => {
        const s = (params.status as string) || '';
        if (s === 'active' || s === 'Live' || s === 'live') return 'active';
        if (s === 'inactive' || s === 'Draft' || s === 'draft') return 'inactive';
        if (s === 'archived' || s === 'Archived') return 'archived';
        return 'all';
    },
    set: (val: string) => {
        if (val === 'all') {
            params.status = '';
        } else if (val === 'Live' || val === 'active') {
            params.status = 'active';
        } else if (val === 'Draft' || val === 'inactive') {
            params.status = 'inactive';
        } else if (val === 'Archived' || val === 'archived') {
            params.status = 'archived';
        } else {
            params.status = val;
        }
    },
});
const filterMenuOpen = ref(false);
const activeRowActionsMenu = ref<string | number | null>(null);
const actionMenuPosition = ref<{ top: string; left: string }>({ top: '0px', left: '0px' });

function toggleRowMenu(event: MouseEvent, rowId: number) {
    if (activeRowActionsMenu.value === rowId) {
        activeRowActionsMenu.value = null;
        return;
    }
    const target = event.currentTarget as HTMLElement | null;
    if (target) {
        const rect = target.getBoundingClientRect();
        const menuHeight = 140;
        const menuWidth = 144;
        const spaceBelow = window.innerHeight - rect.bottom;
        const top = spaceBelow < menuHeight ? `${Math.max(8, rect.top - menuHeight)}px` : `${rect.bottom + 4}px`;
        const left = `${Math.min(window.innerWidth - menuWidth - 16, Math.max(8, rect.right - menuWidth))}px`;

        actionMenuPosition.value = { top, left };
    }
    activeRowActionsMenu.value = rowId;
}

const selectedCategories = ref<string[]>([]);
const selectedStatuses = ref<string[]>([]);
const minPrice = ref<string>('');
const maxPrice = ref<string>('');
// Track by ID so the computed always reflects the latest props after Inertia
// reloads (e.g. after ProductImageManager uploads/deletes/reorders an image).
const editingProductId = ref<number | null>(null);
const editingProduct = computed(() =>
    editingProductId.value !== null
        ? (props.products.data.find((p) => p.id === editingProductId.value) ?? null)
        : null,
);
const productToDelete = ref<ProductRow | null>(null);
const deleteDialogOpen = ref(false);
const createDrawerOpen = ref(Boolean(props.showCreateModal));

const viewDrawerOpen = ref(false);
const viewingProduct = ref<ProductRow | null>(null);

function openViewDrawer(product: ProductRow) {
    activeRowActionsMenu.value = null;
    viewingProduct.value = product;
    viewDrawerOpen.value = true;
}

function closeViewDrawer() {
    viewDrawerOpen.value = false;
    viewingProduct.value = null;
}

function switchToEditFromView() {
    if (!viewingProduct.value) return;
    const id = viewingProduct.value.id;
    closeViewDrawer();
    editingProductId.value = id;
    createDrawerOpen.value = true;
}

const viewOnHand = computed(() =>
    (viewingProduct.value?.inventory_items ?? []).reduce(
        (total, item) => total + (Number(item.quantity_on_hand) || 0),
        0,
    ),
);

const viewReserved = computed(() =>
    (viewingProduct.value?.inventory_items ?? []).reduce(
        (total, item) => total + (Number(item.quantity_reserved) || 0),
        0,
    ),
);

const viewAvailable = computed(() => viewOnHand.value - viewReserved.value);

const viewIsLowStock = computed(() => {
    if (!viewingProduct.value) return false;
    return viewOnHand.value <= (viewingProduct.value.low_stock_threshold ?? 0);
});

const viewStockByVariant = computed(() => {
    if (!viewingProduct.value) return {};
    return Object.fromEntries(
        (viewingProduct.value.inventory_items ?? []).map((item: any) => [
            item.product_variant_id ?? '',
            item,
        ]),
    );
});

function openCreateModal() {
    editingProductId.value = null;
    createDrawerOpen.value = true;
}

function openEditModal(product: ProductRow) {
    activeRowActionsMenu.value = null;
    editingProductId.value = product.id;
    createDrawerOpen.value = true;
}

function closeDrawer() {
    createDrawerOpen.value = false;
    editingProductId.value = null;
}

function destroyProduct() {
    if (!productToDelete.value) return;

    router.delete(productRoutes.destroy.url(productToDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleteDialogOpen.value = false;
            productToDelete.value = null;
        },
    });
}

watch(
    () => props.showCreateModal,
    (open) => {
        if (open) {
            openCreateModal();
        } else {
            closeDrawer();
        }
    },
);

const breadcrumbs = [
    { label: 'Products' },
    { label: 'Product List' },
];

function getStatusLabel(status?: string) {
    const s = (status ?? '').toLowerCase().trim();
    if (s === 'live' || s === 'active') return 'Live';
    if (s === 'archived' || s === 'discontinued') return 'Archived';
    if (s === 'draft' || s === 'inactive') return 'Draft';
    return status || 'Live';
}

const filterStatuses = computed(() => {
    const unique = new Set((props.options.statuses ?? []).map((st) => getStatusLabel(st)));
    return Array.from(unique);
});

function getCategoryName(category?: { id?: number; name: string } | string | null) {
    if (!category) return '—';
    if (typeof category === 'string') return category;
    return category.name || '—';
}

// Tab Switching Handler matching reference product-list.html 1:1
function setProductTab(tab: string) {
    currentTab.value = tab;
}

// Reactive filtering based on search, categories, statuses, price
const rows = computed(() => {
    const backendData = props.products?.data && props.products.data.length > 0
        ? props.products.data
        : [];

    return backendData.filter((item) => {
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

/**
 * The URL the backend generated for the product's primary image, or null so
 * the cell falls back to a neutral placeholder.
 *
 * Never built here: the path lives in the database and the URL comes from the
 * configured disk, so a component that concatenated one would break the day
 * storage moves to S3.
 */
function getProductImage(row: ProductRow): string | null {
    return row.primary_image?.url ?? null;
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
        <main class="flex flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-7" @click="filterMenuOpen = false; activeRowActionsMenu = null">
            <!-- Page Title & Header Actions -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white">Product List</h1>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ tabCounts.all }} products found. {{ Math.round((tabCounts.active / (tabCounts.all || 1)) * 100) }}% are active.
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
            <div class="flex flex-1 flex-col min-h-[480px] overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-[#09090b]">
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
                                {{ tabCounts.all }}
                            </span>
                        </button>
                        <!-- Live Tab -->
                        <button
                            type="button"
                            class="shrink-0 inline-flex cursor-pointer items-center gap-2 rounded-md px-2.5 py-1.5 text-xs transition-colors"
                            :class="currentTab === 'active' ? 'bg-blue-50/60 font-semibold text-blue-600 dark:bg-blue-950/30 dark:text-blue-400' : 'font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                            @click="setProductTab('active')"
                        >
                            Live
                            <span
                                class="rounded-full px-1.5 py-0.5 text-2xs"
                                :class="currentTab === 'active' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 font-semibold' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 font-medium'"
                            >
                                {{ tabCounts.active }}
                            </span>
                        </button>
                        <!-- Draft Tab -->
                        <button
                            type="button"
                            class="shrink-0 inline-flex cursor-pointer items-center gap-2 rounded-md px-2.5 py-1.5 text-xs transition-colors"
                            :class="currentTab === 'inactive' ? 'bg-blue-50/60 font-semibold text-blue-600 dark:bg-blue-950/30 dark:text-blue-400' : 'font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                            @click="setProductTab('inactive')"
                        >
                            Draft
                            <span
                                class="rounded-full px-1.5 py-0.5 text-2xs"
                                :class="currentTab === 'inactive' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 font-semibold' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 font-medium'"
                            >
                                {{ tabCounts.inactive }}
                            </span>
                        </button>
                        <!-- Archived Tab -->
                        <button
                            type="button"
                            class="shrink-0 inline-flex cursor-pointer items-center gap-2 rounded-md px-2.5 py-1.5 text-xs transition-colors"
                            :class="currentTab === 'archived' ? 'bg-blue-50/60 font-semibold text-blue-600 dark:bg-blue-950/30 dark:text-blue-400' : 'font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                            @click="setProductTab('archived')"
                        >
                            Archived
                            <span
                                class="rounded-full px-1.5 py-0.5 text-2xs"
                                :class="currentTab === 'archived' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 font-semibold' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 font-medium'"
                            >
                                {{ tabCounts.archived }}
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
                                            <label v-for="st in filterStatuses" :key="st" class="flex cursor-pointer items-center gap-2">
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
                <div class="flex-1 overflow-x-auto">
                    <table class="w-full table-fixed border-separate border-spacing-0 caption-bottom text-left text-sm align-middle min-w-[1080px]">
                        <thead>
                            <tr class="bg-zinc-50/50 text-2xs font-semibold text-zinc-500 dark:bg-zinc-800/40 dark:text-zinc-400">
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
                                v-for="(row, index) in rows"
                                :key="row.id"
                                class="cursor-pointer transition-colors hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40"
                            >
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

                                <!-- 1:1 Table Action Cell with 3-Dot MoreVertical Dropdown Menu -->
                                <td class="relative border-b border-zinc-200 px-3 py-3.5 text-center align-middle dark:border-zinc-800" @click.stop>
                                    <div class="relative inline-block text-start">
                                        <button
                                            type="button"
                                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                            @click="toggleRowMenu($event, row.id)"
                                        >
                                            <MoreVertical class="size-4" />
                                        </button>

                                        <Teleport to="body">
                                            <div
                                                v-if="activeRowActionsMenu === row.id"
                                                class="fixed z-[9999] w-36 overflow-hidden rounded-md border border-zinc-200 bg-white p-1 text-zinc-900 shadow-md dark:border-zinc-800 dark:bg-[#18181b] dark:text-zinc-100"
                                                :style="{ top: actionMenuPosition.top, left: actionMenuPosition.left }"
                                                @click.stop
                                            >
                                                <div class="select-none px-2 py-1.5 text-xs font-medium text-zinc-400 dark:text-zinc-500">
                                                    Actions
                                                </div>
                                                <div class="-mx-1 my-1 h-px bg-zinc-200 dark:bg-zinc-800"></div>
                                                <!-- 1. View Action -->
                                                <button
                                                    type="button"
                                                    class="relative flex w-full cursor-pointer select-none items-center gap-2 rounded-md px-2 py-1.5 text-xs text-zinc-700 outline-hidden transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800/80 dark:hover:text-white"
                                                    @click="activeRowActionsMenu = null; openViewDrawer(row)"
                                                >
                                                    <Eye class="size-3.5 opacity-60" />
                                                    <span>View</span>
                                                </button>
                                                <!-- 2. Edit Action -->
                                                <button
                                                    type="button"
                                                    class="relative flex w-full cursor-pointer select-none items-center gap-2 rounded-md px-2 py-1.5 text-xs text-zinc-700 outline-hidden transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800/80 dark:hover:text-white"
                                                    @click="activeRowActionsMenu = null; openEditModal(row)"
                                                >
                                                    <Pencil class="size-3.5 opacity-60" />
                                                    <span>Edit</span>
                                                </button>
                                                <div class="-mx-1 my-1 h-px bg-zinc-200 dark:bg-zinc-800"></div>
                                                <!-- 3. Delete Action -->
                                                <button
                                                    type="button"
                                                    class="relative flex w-full cursor-pointer select-none items-center gap-2 rounded-md px-2 py-1.5 text-xs text-rose-600 outline-hidden transition-colors hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                                    @click="activeRowActionsMenu = null; productToDelete = row; deleteDialogOpen = true"
                                                >
                                                    <Trash2 class="size-3.5 opacity-60" />
                                                    <span>Delete</span>
                                                </button>
                                            </div>
                                        </Teleport>
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
            :title="editingProduct ? 'Edit product' : 'Create product'"
            :description="
                editingProduct
                    ? 'Update product details, pricing, classification, and variants.'
                    : 'Add a product to the catalogue. Stock is added separately, through receiving or an adjustment.'
            "
            size="xl"
            @update:open="!$event ? closeDrawer() : null"
        >
            <div class="py-2">
                <ProductForm
                    :key="editingProduct?.id ?? 'new'"
                    :product="editingProduct ?? undefined"
                    :options="props.options"
                    :action="
                        editingProduct
                            ? productRoutes.update.url(editingProduct.id)
                            : productRoutes.store.url()
                    "
                    :method="editingProduct ? 'put' : 'post'"
                    :submit-label="editingProduct ? 'Save changes' : 'Create product'"
                    @cancel="closeDrawer"
                />
            </div>
        </Drawer>

        <!-- View Product Details Drawer -->
        <Drawer
            :open="viewDrawerOpen"
            :title="viewingProduct?.name ?? 'Product details'"
            :description="viewingProduct?.sku ? 'SKU: ' + viewingProduct.sku : 'Catalogue product overview and stock'"
            size="lg"
            @update:open="viewDrawerOpen = $event"
        >
            <template #header-actions>
                <span
                    v-if="viewingProduct"
                    class="inline-flex items-center rounded px-2.5 py-1 text-xs font-semibold"
                    :class="getStatusBadgeClass(viewingProduct.status)"
                >
                    {{ getStatusLabel(viewingProduct.status) }}
                </span>
                <button
                    v-if="viewingProduct"
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                    @click="switchToEditFromView"
                >
                    <Pencil class="size-3" />
                    <span>Edit</span>
                </button>
            </template>

            <div v-if="viewingProduct" class="space-y-6 py-2">
                <!-- Quick Stats 4-Card Grid -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50/50 p-3.5 dark:border-zinc-800 dark:bg-zinc-900/50">
                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Selling Price</p>
                        <p class="mt-1 text-base font-semibold text-zinc-900 dark:text-white">
                            {{ money(viewingProduct.selling_price) }}
                        </p>
                        <p v-if="viewingProduct.cost_price" class="mt-0.5 text-2xs text-zinc-500 dark:text-zinc-400">
                            Cost: {{ money(viewingProduct.cost_price) }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50/50 p-3.5 dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Stock on Hand</p>
                            <span
                                v-if="viewIsLowStock"
                                class="rounded bg-rose-500/10 px-1.5 py-0.5 text-3xs font-semibold text-rose-600 dark:text-rose-400"
                            >
                                Low stock
                            </span>
                        </div>
                        <p class="mt-1 text-base font-semibold text-zinc-900 dark:text-white">
                            {{ viewOnHand }} units
                        </p>
                        <p class="mt-0.5 text-2xs text-zinc-500 dark:text-zinc-400">
                            Threshold: {{ viewingProduct.low_stock_threshold ?? 0 }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50/50 p-3.5 dark:border-zinc-800 dark:bg-zinc-900/50">
                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Available</p>
                        <p class="mt-1 text-base font-semibold text-zinc-900 dark:text-white">
                            {{ viewAvailable }} units
                        </p>
                        <p class="mt-0.5 text-2xs text-zinc-500 dark:text-zinc-400">
                            Reserved: {{ viewReserved }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50/50 p-3.5 dark:border-zinc-800 dark:bg-zinc-900/50">
                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Classification</p>
                        <p class="mt-1 text-base font-semibold text-zinc-900 dark:text-white capitalize">
                            {{ viewingProduct.type ?? 'simple' }}
                        </p>
                        <p class="mt-0.5 text-2xs text-zinc-500 dark:text-zinc-400">
                            {{ (viewingProduct.variants?.length ?? viewingProduct.variants_count ?? 0) }} variants
                        </p>
                    </div>
                </div>

                <!-- Product Information Breakdown -->
                <div class="rounded-lg border border-zinc-200 bg-white p-4 space-y-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Product Information
                    </h4>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2 text-xs">
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">Category</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">
                                {{ getCategoryName(viewingProduct.category) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">Primary Supplier</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">
                                {{ viewingProduct.primary_supplier?.company_name ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">Created</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">
                                {{ date(viewingProduct.created_at) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">Last Updated</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">
                                {{ date(viewingProduct.updated_at) }}
                            </dd>
                        </div>
                        <div v-if="viewingProduct.uuid" class="sm:col-span-2">
                            <dt class="text-zinc-500 dark:text-zinc-400">Public UUID</dt>
                            <dd class="mt-0.5 font-mono text-2xs text-zinc-500 dark:text-zinc-400 truncate">
                                {{ viewingProduct.uuid }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Description (if present) -->
                <div v-if="viewingProduct.description" class="rounded-lg border border-zinc-200 bg-white p-4 space-y-2 dark:border-zinc-800 dark:bg-zinc-900">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Description
                    </h4>
                    <p class="text-xs leading-relaxed text-zinc-700 dark:text-zinc-300 whitespace-pre-line">
                        {{ viewingProduct.description }}
                    </p>
                </div>

                <!-- Variants Table (if variable product or has variants) -->
                <div v-if="viewingProduct.variants && viewingProduct.variants.length > 0" class="rounded-lg border border-zinc-200 bg-white overflow-hidden dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 bg-zinc-50/50 px-4 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/50">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Variants ({{ viewingProduct.variants.length }})
                        </h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-start text-xs">
                            <thead class="border-b border-zinc-200 bg-zinc-50/20 text-2xs font-semibold uppercase tracking-wider text-zinc-400 dark:border-zinc-800 dark:bg-zinc-900/20 dark:text-zinc-400">
                                <tr>
                                    <th class="px-4 py-2.5 text-start">SKU</th>
                                    <th class="px-4 py-2.5 text-start">Name</th>
                                    <th class="px-4 py-2.5 text-end">Price</th>
                                    <th class="px-4 py-2.5 text-center">On Hand</th>
                                    <th class="px-4 py-2.5 text-center">Reserved</th>
                                    <th class="px-4 py-2.5 text-start">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                <tr
                                    v-for="variant in viewingProduct.variants"
                                    :key="variant.id"
                                    class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                >
                                    <td class="px-4 py-2.5 font-mono text-2xs text-zinc-500 dark:text-zinc-400">
                                        {{ variant.sku || '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 font-medium text-zinc-900 dark:text-white">
                                        {{ variant.name }}
                                    </td>
                                    <td class="px-4 py-2.5 text-end font-medium text-zinc-900 dark:text-white">
                                        {{ money(variant.selling_price) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center font-medium text-zinc-900 dark:text-white">
                                        {{ viewStockByVariant[variant.id]?.quantity_on_hand ?? 0 }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center text-zinc-500 dark:text-zinc-400">
                                        {{ viewStockByVariant[variant.id]?.quantity_reserved ?? 0 }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span
                                            class="inline-flex items-center rounded px-1.5 py-0.5 text-3xs font-semibold"
                                            :class="getStatusBadgeClass(variant.status)"
                                        >
                                            {{ getStatusLabel(variant.status) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Linked Suppliers Section -->
                <div v-if="viewingProduct.suppliers && viewingProduct.suppliers.length > 0" class="rounded-lg border border-zinc-200 bg-white overflow-hidden dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 bg-zinc-50/50 px-4 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/50">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Linked Suppliers ({{ viewingProduct.suppliers.length }})
                        </h4>
                    </div>
                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-800 text-xs">
                        <li
                            v-for="supplier in viewingProduct.suppliers"
                            :key="supplier.id"
                            class="flex items-center justify-between px-4 py-2.5 text-zinc-700 dark:text-zinc-300"
                        >
                            <span>{{ supplier.company_name }}</span>
                            <span
                                v-if="supplier.id === viewingProduct.primary_supplier?.id"
                                class="inline-flex items-center rounded bg-blue-50 px-1.5 py-0.5 text-3xs font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-400"
                            >
                                Primary
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <template #footer>
                <button
                    type="button"
                    class="cursor-pointer rounded-md border border-zinc-200 bg-white px-3.5 py-1.5 text-xs font-medium text-zinc-700 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                    @click="closeViewDrawer"
                >
                    Close
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-md bg-zinc-950 px-3.5 py-1.5 text-xs font-medium text-white shadow-xs transition-colors hover:bg-zinc-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-100"
                    @click="switchToEditFromView"
                >
                    Edit product
                </button>
            </template>
        </Drawer>

        <!-- Delete Confirmation Alert Dialog -->
        <AlertDialog
            :open="deleteDialogOpen"
            @update:open="deleteDialogOpen = $event"
        >
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This action cannot be undone. This will permanently delete the product
                        "<span class="font-semibold text-foreground">{{ productToDelete?.name }}</span>"
                        and remove all variants from our servers.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="deleteDialogOpen = false">
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-rose-600 hover:bg-rose-700 text-white"
                        @click="destroyProduct"
                    >
                        Delete product
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

    </AppLayout>
</template>
