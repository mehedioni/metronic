<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    CloudUpload,
    MoreVertical,
    Pencil,
    Plus,
    Search,
    Settings,
    SlidersHorizontal,
    Star,
    Trash2,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import Pagination from '@/components/Pagination.vue';
import { useCsvExport } from '@/composables/useCsvExport';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { money, number } from '@/lib/format';
import productRoutes from '@/routes/inventory/products';
import type { Paginated } from '@/types';

interface InventoryRow {
    quantity_on_hand: number;
    quantity_reserved: number;
}

interface ProductRow {
    id: string | number;
    name: string;
    sku: string | null;
    status: string;
    type?: string;
    selling_price?: string | number | null;
    price?: string;
    rawPrice?: number;
    cost_price?: string | number | null;
    variants_count?: number;
    low_stock_threshold?: number;
    rating?: number | string;
    created?: string;
    updated?: string;
    created_at?: string;
    updated_at?: string;
    created_formatted?: string;
    updated_formatted?: string;
    image?: string;
    image_url?: string;
    category?: { id?: string; name: string } | string | null;
    primary_supplier?: { id?: string; company_name: string } | null;
    inventory_items?: InventoryRow[];
}

const props = defineProps<{
    products: Paginated<ProductRow>;
    filters: Record<string, unknown>;
    options?: {
        categories?: Array<{ id: string; name: string }>;
        suppliers?: Array<{ id: string; company_name: string }>;
        statuses?: string[];
        types?: string[];
    };
    showCreateModal?: boolean;
}>();

const { can } = usePermissions();
const { exportRows } = useCsvExport();

const { params, loading, toggleSort, sortState, reset } = useTableQuery({
    url: productRoutes.index.url(),
    filters: props.filters,
    only: ['products', 'filters'],
});

// Reference 1:1 Stock Data from store-inventory/js/inventory.js
const referenceStockData: ProductRow[] = [
    { id: 1, name: 'Premium Comfort Max', sku: 'PC-5678', image: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=120&auto=format&fit=crop&q=80', category: 'Sneakers', price: '$89.00', rawPrice: 89.00, status: 'Must Act', rating: '5.0', created: '31 Jul, 2025', updated: '31 Jul, 2025' },
    { id: 2, name: 'Sport Performance Pro', sku: 'SP-8901', image: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=120&auto=format&fit=crop&q=80', category: 'Outdoor', price: '$112.50', rawPrice: 112.50, status: 'Live', rating: '5.0', created: '30 Jul, 2025', updated: '30 Jul, 2025' },
    { id: 3, name: 'Classic Retro Style', sku: 'CR-1234', image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=120&auto=format&fit=crop&q=80', category: 'Sneakers', price: '$63.75', rawPrice: 63.75, status: 'Archived', rating: '5.0', created: '29 Jul, 2025', updated: '29 Jul, 2025' },
    { id: 4, name: 'Adventure Explorer', sku: 'AE-4567', image: 'https://images.unsplash.com/photo-1560343090-f0409e92791a?w=120&auto=format&fit=crop&q=80', category: 'Outdoor', price: '$98.00', rawPrice: 98.00, status: 'Live', rating: '5.0', created: '28 Jul, 2025', updated: '28 Jul, 2025' },
    { id: 5, name: 'Modern Street Elite', sku: 'MS-7890', image: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=120&auto=format&fit=crop&q=80', category: 'Sneakers', price: '$76.25', rawPrice: 76.25, status: 'Draft', rating: '5.0', created: '27 Jul, 2025', updated: '27 Jul, 2025' },
    { id: 6, name: 'Eco Friendly Runner', sku: 'EF-2345', image: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=120&auto=format&fit=crop&q=80', category: 'Runners', price: '$82.50', rawPrice: 82.50, status: 'Live', rating: '5.0', created: '26 Jul, 2025', updated: '26 Jul, 2025' },
    { id: 7, name: 'Luxury Comfort Pro', sku: 'LC-5678', image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=120&auto=format&fit=crop&q=80', category: 'Sneakers', price: '$145.00', rawPrice: 145.00, status: 'Live', rating: '5.0', created: '25 Jul, 2025', updated: '25 Jul, 2025' },
    { id: 8, name: 'Tech Smart Runner', sku: 'TS-8901', image: 'https://images.unsplash.com/photo-1560343090-f0409e92791a?w=120&auto=format&fit=crop&q=80', category: 'Runners', price: '$91.99', rawPrice: 91.99, status: 'Must Act', rating: '5.0', created: '24 Jul, 2025', updated: '24 Jul, 2025' },
    { id: 9, name: 'Nike Air Max 270 React E...', sku: 'WM-8421', image: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=120&auto=format&fit=crop&q=80', category: 'Sneakers', price: '$83.00', rawPrice: 83.00, status: 'Live', rating: '5.0', created: '18 Aug, 2025', updated: '18 Aug, 2025' },
    { id: 10, name: 'Trail Runner Z2', sku: 'UC-3990', image: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=120&auto=format&fit=crop&q=80', category: 'Outdoor', price: '$110.00', rawPrice: 110.00, status: 'Live', rating: '5.0', created: '17 Aug, 2025', updated: '17 Aug, 2025' },
];

const currentTab = ref<string>('all');
const filterMenuOpen = ref(false);
const activeRowActionsMenu = ref<string | number | null>(null);

const selectedCategories = ref<string[]>([]);
const selectedStatuses = ref<string[]>([]);
const minPrice = ref<string>('');
const maxPrice = ref<string>('');

// Smooth drawer state management matching Stock/Planner.vue
const isCreateModalMounted = ref(false);
const isCreateModalVisible = ref(false);

function openCreateModal() {
    isCreateModalMounted.value = true;
    setTimeout(() => {
        isCreateModalVisible.value = true;
    }, 20);
}

function closeCreateModal() {
    isCreateModalVisible.value = false;
    setTimeout(() => {
        isCreateModalMounted.value = false;
    }, 300);
}

onMounted(() => {
    if (props.showCreateModal) {
        openCreateModal();
    }
});

watch(
    () => props.showCreateModal,
    (val) => {
        if (val) {
            openCreateModal();
        } else {
            closeCreateModal();
        }
    },
);

const breadcrumbs = [
    { label: 'Products' },
    { label: 'Product List' },
];

function getStatusLabel(status?: string) {
    const s = (status || 'Live').toLowerCase().trim();
    if (s === 'live' || s === 'active') return 'Live';
    if (s === 'must act' || s === 'must_act' || s === 'action needed' || s === 'action_needed' || s === 'out_of_stock') return 'Must Act';
    if (s === 'archived' || s === 'discontinued') return 'Archived';
    if (s === 'draft' || s === 'inactive') return 'Draft';
    return status || 'Live';
}

function getCategoryName(category?: { id?: string; name: string } | string | null) {
    if (!category) return 'Sneakers';
    if (typeof category === 'string') return category;
    return category.name || 'Sneakers';
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

    // Check if backend data contains products for the current tab
    const backendMatchingTab = backendData.filter((item) => {
        if (currentTab.value !== 'all') {
            const st = getStatusLabel(item.status);
            return st === currentTab.value || item.status === currentTab.value;
        }
        return true;
    });

    // Use backend data if matching products exist, otherwise use reference demo data so every tab shows products
    const sourceData = (backendData.length > 0 && backendMatchingTab.length > 0)
        ? backendData
        : referenceStockData;

    return sourceData.filter((item) => {
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

function getProductImage(row: ProductRow, idx: number) {
    if (row.image) return row.image;
    if (row.image_url) return row.image_url;
    return referenceStockData[idx % referenceStockData.length].image;
}

function formatPrice(row: ProductRow) {
    if (row.price) return row.price;
    if (row.selling_price !== undefined && row.selling_price !== null) {
        const num = typeof row.selling_price === 'number' ? row.selling_price : parseFloat(row.selling_price);
        if (!isNaN(num)) return money(num);
    }
    return '$89.00';
}

// Modal Form State
const createForm = ref({
    name: '',
    sku: '',
    barcode: '',
    description: '',
    category_id: '',
    brand: 'Nike',
    status: 'active',
    is_featured: false,
    tags: [] as string[],
    variants: [] as any[],
});
const tagInput = ref('');
function addTag() {
    const val = tagInput.value.trim();
    if (val && !createForm.value.tags.includes(val)) {
        createForm.value.tags.push(val);
        tagInput.value = '';
    }
}
function removeTag(tag: string) {
    createForm.value.tags = createForm.value.tags.filter((t) => t !== tag);
}
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
                        class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                    >
                        <Upload class="size-4" />
                        <span>Import</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md bg-zinc-950 px-3.5 text-[0.8125rem] font-medium text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-black dark:hover:bg-white"
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
                                class="rounded-full px-1.5 py-0.5 text-[10px]"
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
                                class="rounded-full px-1.5 py-0.5 text-[10px]"
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
                                class="rounded-full px-1.5 py-0.5 text-[10px]"
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
                                class="rounded-full px-1.5 py-0.5 text-[10px]"
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
                                class="rounded-full px-1.5 py-0.5 text-[10px]"
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
                                class="h-8.5 w-full rounded-md border border-zinc-200 bg-white ps-9 pe-3 text-[0.8125rem] text-zinc-900 placeholder-zinc-400 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                            />
                        </div>

                        <!-- Filters Dropdown Popover Button -->
                        <div class="relative" @click.stop>
                            <button
                                type="button"
                                class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md border bg-white px-3 text-[0.8125rem] font-medium shadow-xs transition-colors hover:bg-zinc-50 dark:bg-zinc-800 dark:hover:bg-zinc-700/60"
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
                                    <button type="button" class="cursor-pointer text-[11px] font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400" @click="resetProductFilters">Reset all</button>
                                </div>

                                <div class="max-h-[360px] space-y-4 overflow-y-auto p-4">
                                    <!-- Category Filter -->
                                    <div class="space-y-2">
                                        <label class="block text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">Category</label>
                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <label v-for="cat in ['Sneakers', 'Outdoor', 'Runners', 'Apparel']" :key="cat" class="flex cursor-pointer items-center gap-2">
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
                                        <label class="block text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">Status</label>
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
                                        <label class="block text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">Price Range ($)</label>
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
                            <tr class="bg-zinc-50/50 text-[11px] font-semibold text-zinc-500 dark:bg-zinc-800/40 dark:text-zinc-400">
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
                                <th class="h-10 w-[95px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <button type="button" class="group -ms-2 inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white">
                                        <span>Rating</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
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
                                v-for="(row, idx) in rows"
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
                                            <img :src="getProductImage(row, idx)" :alt="row.name" class="size-full object-contain" />
                                        </div>
                                        <div class="flex min-w-0 flex-col gap-0.5">
                                            <span class="truncate text-sm font-medium text-zinc-900 transition-colors hover:text-blue-600 dark:text-white dark:hover:text-blue-400">{{ row.name }}</span>
                                            <span class="inline-flex items-center gap-1 text-xs">
                                                <span class="font-mono text-[11px] text-zinc-400 uppercase">SKU:</span>
                                                <span class="font-mono text-[11px] font-medium text-zinc-700 dark:text-zinc-300">{{ row.sku || 'PC-5678' }}</span>
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
                                <td class="border-b border-e border-zinc-200 px-4 py-3.5 align-middle dark:border-zinc-800">
                                    <span class="inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950/60 dark:text-amber-400">
                                        <Star class="size-3 fill-amber-400 text-amber-400" />
                                        {{ row.rating || '5.0' }}
                                    </span>
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3.5 align-middle text-sm font-normal text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">
                                    {{ row.created || row.created_formatted || '31 Jul, 2025' }}
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3.5 align-middle text-sm font-normal text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">
                                    {{ row.updated || row.updated_formatted || '31 Jul, 2025' }}
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

        <!-- CREATE PRODUCT SLIDE-OVER DRAWER (Matches Stock/Planner.vue 1:1 with z-50 backdrop covering sidebar) -->
        <div
            v-if="isCreateModalMounted"
            class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs transition-opacity duration-300"
            :class="isCreateModalVisible ? 'opacity-100' : 'opacity-0'"
            @click="closeCreateModal"
        />

        <div
            v-if="isCreateModalMounted"
            role="dialog"
            aria-modal="true"
            class="fixed top-0 end-0 bottom-0 z-50 flex h-full w-full flex-col overflow-hidden border-s border-zinc-200 bg-white shadow-2xl transition-transform duration-300 ease-in-out dark:border-zinc-800 dark:bg-[#121215] lg:w-[1080px]"
            :class="isCreateModalVisible ? 'translate-x-0' : 'translate-x-full'"
        >
            <!-- Drawer Header -->
            <div class="flex shrink-0 items-center justify-between border-b border-zinc-200 bg-white px-6 py-3.5 dark:border-zinc-800 dark:bg-[#121215]">
                <h2 class="text-base font-medium text-zinc-900 dark:text-white">
                    Create New Product
                </h2>
                <button
                    type="button"
                    class="cursor-pointer rounded-md p-1 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                    @click="closeCreateModal"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Subheader Action Toolbar -->
            <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-[#121215]">
                <div class="relative w-[140px]">
                    <select
                        v-model="createForm.status"
                        class="inline-flex h-8.5 w-full cursor-pointer items-center justify-between rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    >
                        <option value="active">Select Status</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <div class="flex items-center gap-2.5 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                    <span>Read about <a class="text-blue-600 hover:underline dark:text-blue-400" href="javascript:void(0)">How to Create Product</a></span>
                    <button
                        type="button"
                        class="inline-flex h-8.5 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                        @click="closeCreateModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md bg-zinc-950 px-3 text-[0.8125rem] font-medium text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-black dark:hover:bg-white"
                        @click="closeCreateModal"
                    >
                        Create
                    </button>
                </div>
            </div>

            <!-- Drawer Body (Scrollable Form) -->
            <div class="flex-1 overflow-y-auto p-0">
                <div class="flex flex-wrap lg:flex-nowrap">
                    <!-- Left Column: Basic Info, Category & Brand, Variants -->
                    <div class="grow space-y-5 py-5 ps-4.5 pe-4.5 border-zinc-200 dark:border-zinc-800 lg:border-e lg:pe-5">
                        <!-- Basic Info Card -->
                        <div class="flex flex-col items-stretch overflow-hidden rounded-md border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-[#18181b]">
                            <div class="flex min-h-[38px] items-center justify-between border-b border-zinc-200 bg-zinc-50/60 px-5 dark:border-zinc-800 dark:bg-zinc-900/50">
                                <h3 class="text-xs font-semibold tracking-tight text-zinc-900 dark:text-white">Basic Info</h3>
                                <div class="flex items-center gap-2">
                                    <label class="cursor-pointer text-xs font-medium text-zinc-700 dark:text-zinc-300" @click="createForm.is_featured = !createForm.is_featured">Featured</label>
                                    <button
                                        type="button"
                                        class="relative inline-flex h-5 w-8 shrink-0 cursor-pointer items-center rounded-full transition-colors"
                                        :class="createForm.is_featured ? 'bg-blue-600' : 'bg-zinc-200 dark:bg-zinc-700'"
                                        @click="createForm.is_featured = !createForm.is_featured"
                                    >
                                        <span
                                            class="pointer-events-none block size-3.5 rounded-full bg-white shadow transition-transform"
                                            :class="createForm.is_featured ? 'translate-x-[14px]' : 'translate-x-[2px]'"
                                        />
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-3 p-5 pt-4">
                                <div class="mb-3 flex flex-col gap-2">
                                    <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Product Name</label>
                                    <input
                                        v-model="createForm.name"
                                        type="text"
                                        placeholder="Product Name"
                                        class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] text-zinc-900 placeholder-zinc-400 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                    />
                                </div>

                                <div class="mb-2.5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">SKU</label>
                                        <input
                                            v-model="createForm.sku"
                                            type="text"
                                            placeholder="SKU"
                                            class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 font-mono text-[0.8125rem] text-zinc-900 placeholder-zinc-400 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                        />
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Barcode</label>
                                        <input
                                            v-model="createForm.barcode"
                                            type="text"
                                            placeholder="Barcode"
                                            class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 font-mono text-[0.8125rem] text-zinc-900 placeholder-zinc-400 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                        />
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Product Description</label>
                                    <textarea
                                        v-model="createForm.description"
                                        rows="3"
                                        placeholder="Product Description"
                                        class="min-h-[100px] w-full rounded-md border border-zinc-200 bg-white p-3 text-[0.8125rem] text-zinc-900 placeholder-zinc-400 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Category & Brand Card -->
                        <div class="flex flex-col items-stretch overflow-hidden rounded-md border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-[#18181b]">
                            <div class="flex min-h-[38px] items-center justify-between border-b border-zinc-200 bg-zinc-50/60 px-5 dark:border-zinc-800 dark:bg-zinc-900/50">
                                <h3 class="text-xs font-semibold tracking-tight text-zinc-900 dark:text-white">Category & Brand</h3>
                            </div>
                            <div class="space-y-3 p-5 pt-4">
                                <div class="flex flex-col gap-2">
                                    <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Product Category</label>
                                    <select
                                        v-model="createForm.category_id"
                                        class="h-8.5 w-full cursor-pointer rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                    >
                                        <option value="">Select Category</option>
                                        <option value="sneakers">Sneakers</option>
                                        <option value="outdoor">Outdoor</option>
                                        <option value="runners">Runners</option>
                                        <option value="apparel">Apparel</option>
                                    </select>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Product Brand</label>
                                    <select
                                        v-model="createForm.brand"
                                        class="h-8.5 w-full cursor-pointer rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                    >
                                        <option value="Select Brand">Select Brand</option>
                                        <option value="Nike">Nike</option>
                                        <option value="Adidas">Adidas</option>
                                        <option value="Puma">Puma</option>
                                        <option value="New Balance">New Balance</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Variants Card -->
                        <div class="flex flex-col items-stretch overflow-hidden rounded-md border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-[#18181b]">
                            <div class="flex min-h-[40px] items-center justify-between border-b border-zinc-200 bg-zinc-50/60 px-5 dark:border-zinc-800 dark:bg-zinc-900/50">
                                <h3 class="text-xs font-semibold tracking-tight text-zinc-900 dark:text-white">Variants</h3>
                                <div class="flex items-center gap-3.5">
                                    <button type="button" class="-mb-px inline-flex cursor-pointer items-center justify-center border-b border-zinc-950 py-1.5 text-xs font-medium text-zinc-950 dark:border-zinc-100 dark:text-zinc-100">
                                        Variants
                                    </button>
                                    <button type="button" class="inline-flex cursor-pointer items-center justify-center py-1.5 text-xs font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                        Add New
                                    </button>
                                    <Settings class="size-4 text-zinc-400" />
                                </div>
                            </div>

                            <div class="p-10 text-center sm:text-left">
                                <h3 class="text-sm font-medium leading-7 text-zinc-900 dark:text-white">No variants to display</h3>
                                <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">Set up different options for this product</span>
                                <div class="mt-3.5">
                                    <button
                                        type="button"
                                        class="inline-flex h-7 cursor-pointer items-center justify-center gap-1.25 rounded-md bg-zinc-950 px-2.5 text-xs font-medium text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-black dark:hover:bg-white"
                                    >
                                        <Plus class="mr-1 size-3.5" />
                                        Add Variant
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Media Upload & Tags -->
                    <div class="w-full shrink-0 space-y-5 p-5 lg:w-[420px] lg:mt-5 lg:ps-5">
                        <!-- Dropzone Card -->
                        <div class="flex flex-col items-stretch rounded-md border border-dashed border-zinc-300 bg-zinc-50/50 shadow-none transition-colors dark:border-zinc-700 dark:bg-zinc-900/30">
                            <div class="grow p-5 text-center">
                                <div class="mx-auto mb-3 flex size-[32px] items-center justify-center rounded-full border border-zinc-200 dark:border-zinc-700">
                                    <CloudUpload class="size-4 text-zinc-600 dark:text-zinc-300" />
                                </div>
                                <h3 class="mb-0.5 text-xs font-semibold text-zinc-900 dark:text-white">Choose a file or drag & drop here.</h3>
                                <span class="mb-3 block text-[11px] font-normal text-zinc-500 dark:text-zinc-400">JPEG, PNG, up to 5 MB.</span>
                                <button type="button" class="inline-flex h-7 cursor-pointer items-center justify-center gap-1.25 rounded-md bg-zinc-950 px-2.5 text-xs font-medium text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-black dark:hover:bg-white">
                                    Browse File
                                </button>
                            </div>
                        </div>

                        <div class="h-px w-full bg-zinc-200 dark:bg-zinc-800" />

                        <!-- Tags Input -->
                        <div>
                            <div class="mb-2.5 flex flex-col gap-2.5">
                                <label class="text-xs font-medium leading-3 text-zinc-700 dark:text-zinc-300">Tags</label>
                                <input
                                    v-model="tagInput"
                                    type="text"
                                    placeholder="Add tags (press Enter or comma)"
                                    class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                    @keydown.enter.prevent="addTag"
                                />
                            </div>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span
                                    v-for="t in createForm.tags"
                                    :key="t"
                                    class="inline-flex items-center gap-1 rounded-md bg-zinc-100 px-2 py-1 text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                                >
                                    {{ t }}
                                    <X class="size-3 cursor-pointer" @click="removeTag(t)" />
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Drawer Footer -->
            <div class="flex shrink-0 items-center justify-between border-t border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-[#121215]">
                <div class="relative w-[140px]">
                    <select
                        v-model="createForm.status"
                        class="inline-flex h-8.5 w-full cursor-pointer items-center justify-between rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    >
                        <option value="active">Select Status</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-8.5 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                        @click="closeCreateModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-8.5 cursor-pointer items-center justify-center rounded-md bg-zinc-950 px-3 text-[0.8125rem] font-medium text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-black dark:hover:bg-white"
                        @click="closeCreateModal"
                    >
                        Create
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
