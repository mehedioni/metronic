<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ChevronsUpDown,
    Layers,
    LogIn,
    LogOut,
    MoreVertical,
    Search,
    SlidersHorizontal,
    SquarePen,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { FormField, Textarea } from '@/components/form';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { usePageErrors } from '@/composables/usePageErrors';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, money, number } from '@/lib/format';
import { humanize } from '@/lib/status';
import products from '@/routes/inventory/products';
import stock from '@/routes/inventory/stock';
import type { Paginated } from '@/types';

interface StockRow {
    id: number;
    product_id: number;
    product_variant_id: number | null;
    quantity_on_hand: number;
    quantity_reserved: number;
    updated_at: string;
    product: {
        id: number;
        name: string;
        sku: string | null;
        cost_price: string | number | null;
        selling_price: string | number | null;
        image_path: string | null;
        low_stock_threshold: number;
        category: { id: number; name: string } | null;
    };
    variant: { id: number; sku: string; name: string } | null;
}

const props = defineProps<{
    items: Paginated<StockRow>;
    summary?: StockSummary;
    filters: Record<string, unknown>;
    categories: Array<{ id: number; name: string }>;
    movementTypes: string[];
}>();

const { can } = usePermissions();
const { firstOf } = usePageErrors();

const { params, loading, toggleSort, reset } = useTableQuery({
    url: stock.index.url(),
    filters: props.filters,
    only: ['items', 'filters', 'summary'],
});

const rows = computed(() => props.items.data);
const adjusting = ref<StockRow | null>(null);
const activeRowMenu = ref<string | null>(null);
const selectedRows = ref<string[]>([]);
const selectAll = ref(false);

const categoryMenuOpen = ref(false);
const supplierMenuOpen = ref(false);
const categorySearch = ref('');
const supplierSearch = ref('');

const adjustForm = useForm({
    product_id: '',
    product_variant_id: '' as string | null,
    type: 'adjustment_increase',
    quantity: 1,
    reason: '',
});

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Inventory' },
    { label: 'All Stock' },
];

const supplierPalette = [
    '#F59E0B',
    '#3B82F6',
    '#10B981',
    '#EF4444',
    '#8B5CF6',
    '#F97316',
    '#06B6D4',
    '#EAB308',
    '#EC4899',
    '#6366F1',
];

function getSupplierColor(name?: string): string {
    if (!name) return '#71717A';
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return supplierPalette[Math.abs(hash) % supplierPalette.length];
}

function getProductImage(item: StockRow, index: number): string {
    if (item.product?.image_path) {
        return item.product.image_path.startsWith('/') || item.product.image_path.startsWith('http')
            ? item.product.image_path
            : `/assets/products/${item.product.image_path}`;
    }
    const shoeNum = (index % 10) + 1;
    return `/assets/products/shoe-${shoeNum}.png`;
}

function available(row: StockRow): number {
    return row.quantity_on_hand - row.quantity_reserved;
}

function isLow(row: StockRow): boolean {
    return row.quantity_on_hand > 0 && row.quantity_on_hand <= (row.product?.low_stock_threshold ?? 0);
}

function delta(row: StockRow): { text: string; isPos: boolean } {
    const avail = available(row);
    if (avail > 0) {
        return { text: `+${avail}`, isPos: true };
    } else if (avail < 0) {
        return { text: `${avail}`, isPos: false };
    }
    return { text: '0', isPos: true };
}

function toggleSelectAll() {
    if (selectAll.value) {
        selectedRows.value = rows.value.map((r) => r.id);
    } else {
        selectedRows.value = [];
    }
}

function toggleRowSelect(id: string) {
    const idx = selectedRows.value.indexOf(id);
    if (idx > -1) {
        selectedRows.value.splice(idx, 1);
    } else {
        selectedRows.value.push(id);
    }
    selectAll.value = selectedRows.value.length === rows.value.length;
}

function toggleRowMenu(id: string, event: MouseEvent) {
    event.stopPropagation();
    activeRowMenu.value = activeRowMenu.value === id ? null : id;
}

function closeDropdowns() {
    activeRowMenu.value = null;
    categoryMenuOpen.value = null as any;
    supplierMenuOpen.value = null as any;
}

onMounted(() => {
    window.addEventListener('click', closeDropdowns);
});

onUnmounted(() => {
    window.removeEventListener('click', closeDropdowns);
});

function openAdjust(row: StockRow) {
    // Form fields carry strings: that is what an <input>/<select> round-trips,
    // and Laravel's "integer" rule accepts a numeric string.
    adjustForm.product_id = String(row.product_id);
    adjustForm.product_variant_id =
        row.product_variant_id === null ? null : String(row.product_variant_id);
    adjustForm.type = 'adjustment_increase';
    adjustForm.quantity = 1;
    adjustForm.reason = '';

    adjusting.value = row;
}

function adjust() {
    adjustForm.post(stock.adjust.url(), {
        preserveScroll: true,
        onSuccess: () => (adjusting.value = null),
    });
}

const filteredCategories = computed(() => {
    if (!categorySearch.value.trim()) return props.categories;
    const q = categorySearch.value.toLowerCase();
    return props.categories.filter((c) => c.name.toLowerCase().includes(q));
});

const filteredSuppliers = computed(() => {
    const list = props.suppliers || [];
    if (!supplierSearch.value.trim()) return list;
    const q = supplierSearch.value.toLowerCase();
    return list.filter((s) => s.company_name.toLowerCase().includes(q));
});

const selectedCategoryName = computed(() => {
    if (!params.category_id) return 'Category';
    const found = props.categories.find((c) => c.id === params.category_id);
    return found ? found.name : 'Category';
});

const selectedSupplierName = computed(() => {
    if (!params.supplier_id) return 'Supplier';
    const found = props.suppliers?.find((s) => s.id === params.supplier_id);
    return found ? found.company_name : 'Supplier';
});
</script>

<template>
    <Head title="All stock" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="All stock"
            :description="`${number(props.items.total)} stockable units. Available is on hand minus what confirmed orders have reserved.`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Link
                    :href="stock.planner.url()"
                    class="inline-flex h-8.5 items-center justify-center rounded-md bg-zinc-950 px-3.5 text-[0.8125rem] font-medium text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-zinc-950 dark:hover:bg-white"
                >
                    Stock Planner
                </Link>
            </template>
        </PageHeader>

        <!-- TOP STATS SUMMARY CARD -->
        <div
            v-if="summary"
            class="rounded-xl border border-zinc-200/80 bg-white p-5 shadow-xs dark:border-zinc-800/80 dark:bg-[#121215] lg:p-7.5"
        >
            <div class="flex flex-col items-stretch gap-6 lg:flex-row lg:items-center">
                <!-- Left: Total Asset Value -->
                <div class="flex shrink-0 flex-col gap-1.5">
                    <span class="text-sm font-normal text-zinc-500 dark:text-zinc-400">Total Asset Value</span>
                    <span class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ money(summary.total_asset_value) }}
                    </span>
                </div>

                <!-- Vertical Separator -->
                <div class="mx-2 hidden h-14 w-px shrink-0 bg-zinc-200 dark:bg-zinc-800 lg:block"></div>

                <!-- Right: Products & Stock Distribution Bar -->
                <div class="flex w-full flex-col gap-2">
                    <div class="mb-1 flex items-center gap-2">
                        <span class="text-xl font-semibold leading-none text-zinc-900 dark:text-white">
                            {{ summary.total_products }}
                        </span>
                        <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">products</span>
                    </div>

                    <!-- Segmented Progress Bar -->
                    <div class="mb-2 flex items-center gap-1">
                        <div
                            class="h-2 rounded-xs bg-emerald-500 transition-all"
                            :style="{
                                width: summary.total_products > 0
                                    ? `${Math.max(8, (summary.in_stock / summary.total_products) * 100)}%`
                                    : '100%',
                            }"
                        ></div>
                        <div
                            v-if="summary.low_stock > 0"
                            class="h-2 rounded-xs bg-amber-500 transition-all"
                            :style="{
                                width: `${Math.max(6, (summary.low_stock / summary.total_products) * 100)}%`,
                            }"
                        ></div>
                        <div
                            v-if="summary.out_of_stock > 0"
                            class="h-2 rounded-xs bg-rose-500 transition-all"
                            :style="{
                                width: `${Math.max(6, (summary.out_of_stock / summary.total_products) * 100)}%`,
                            }"
                        ></div>
                    </div>

                    <!-- Legend -->
                    <div class="flex flex-wrap items-center gap-4 text-sm font-normal">
                        <div class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-emerald-500"></span>
                            <span class="text-zinc-600 dark:text-zinc-400">
                                In stock:
                                <span class="ms-0.5 font-semibold text-zinc-900 dark:text-white">
                                    {{ summary.in_stock }}
                                </span>
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-amber-500"></span>
                            <span class="text-zinc-600 dark:text-zinc-400">
                                Low stock:
                                <span class="ms-0.5 font-semibold text-zinc-900 dark:text-white">
                                    {{ summary.low_stock }}
                                </span>
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-rose-500"></span>
                            <span class="text-zinc-600 dark:text-zinc-400">
                                Out of stock:
                                <span class="ms-0.5 font-semibold text-zinc-900 dark:text-white">
                                    {{ summary.out_of_stock }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ALL STOCK DATA TABLE CARD -->
        <div class="overflow-hidden rounded-xl border border-zinc-200/80 bg-white shadow-xs dark:border-zinc-800/80 dark:bg-[#121215]">
            <!-- Toolbar Header -->
            <div class="flex flex-col justify-between gap-3 border-b border-zinc-200 p-5 dark:border-zinc-800 sm:flex-row sm:items-center">
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-48 lg:w-56">
                        <Search class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            v-model="params.search"
                            type="text"
                            placeholder="Search..."
                            class="h-8.5 w-full rounded-md border border-zinc-200 bg-white ps-9 pe-3 text-[0.8125rem] text-zinc-900 placeholder-zinc-400 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                        />
                    </div>

                    <!-- Category Filter Dropdown -->
                    <div class="relative" @click.stop>
                        <button
                            type="button"
                            class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] font-medium text-zinc-700 shadow-xs hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                            @click="categoryMenuOpen = !categoryMenuOpen; supplierMenuOpen = false"
                        >
                            <span>{{ selectedCategoryName }}</span>
                            <ChevronsUpDown class="size-3.5 opacity-60" />
                        </button>
                        <div
                            v-if="categoryMenuOpen"
                            class="absolute start-0 top-full z-50 mt-1.5 w-56 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-xl shadow-black/10 dark:border-zinc-800 dark:bg-[#18181b]"
                        >
                            <div class="flex items-center border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                                <Search class="me-2 size-3.5 shrink-0 text-zinc-400" />
                                <input
                                    v-model="categorySearch"
                                    type="text"
                                    class="w-full bg-transparent text-xs text-zinc-900 placeholder:text-zinc-400 focus:outline-none dark:text-zinc-100"
                                    placeholder="Search category..."
                                />
                            </div>
                            <div class="max-h-60 space-y-0.5 overflow-y-auto p-1.5">
                                <button
                                    type="button"
                                    class="flex w-full cursor-pointer items-center justify-between rounded-md px-2 py-1.5 text-xs text-zinc-800 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800/70"
                                    :class="{ 'font-semibold text-primary': !params.category_id }"
                                    @click="params.category_id = ''; categoryMenuOpen = false"
                                >
                                    <span>All Categories</span>
                                </button>
                                <button
                                    v-for="cat in filteredCategories"
                                    :key="cat.id"
                                    type="button"
                                    class="flex w-full cursor-pointer items-center justify-between rounded-md px-2 py-1.5 text-xs text-zinc-800 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800/70"
                                    :class="{ 'font-semibold text-primary': params.category_id === cat.id }"
                                    @click="params.category_id = cat.id; categoryMenuOpen = false"
                                >
                                    <span>{{ cat.name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Supplier Filter Dropdown -->
                    <div v-if="props.suppliers && props.suppliers.length > 0" class="relative" @click.stop>
                        <button
                            type="button"
                            class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] font-medium text-zinc-700 shadow-xs hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                            @click="supplierMenuOpen = !supplierMenuOpen; categoryMenuOpen = false"
                        >
                            <span>{{ selectedSupplierName }}</span>
                            <ChevronsUpDown class="size-3.5 opacity-60" />
                        </button>
                        <div
                            v-if="supplierMenuOpen"
                            class="absolute start-0 top-full z-50 mt-1.5 w-56 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-xl shadow-black/10 dark:border-zinc-800 dark:bg-[#18181b]"
                        >
                            <div class="flex items-center border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                                <Search class="me-2 size-3.5 shrink-0 text-zinc-400" />
                                <input
                                    v-model="supplierSearch"
                                    type="text"
                                    class="w-full bg-transparent text-xs text-zinc-900 placeholder:text-zinc-400 focus:outline-none dark:text-zinc-100"
                                    placeholder="Search supplier..."
                                />
                            </div>
                            <div class="max-h-60 space-y-0.5 overflow-y-auto p-1.5">
                                <button
                                    type="button"
                                    class="flex w-full cursor-pointer items-center justify-between rounded-md px-2 py-1.5 text-xs text-zinc-800 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800/70"
                                    :class="{ 'font-semibold text-primary': !params.supplier_id }"
                                    @click="params.supplier_id = ''; supplierMenuOpen = false"
                                >
                                    <span>All Suppliers</span>
                                </button>
                                <button
                                    v-for="sup in filteredSuppliers"
                                    :key="sup.id"
                                    type="button"
                                    class="flex w-full cursor-pointer items-center justify-between rounded-md px-2 py-1.5 text-xs text-zinc-800 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800/70"
                                    :class="{ 'font-semibold text-primary': params.supplier_id === sup.id }"
                                    @click="params.supplier_id = sup.id; supplierMenuOpen = false"
                                >
                                    <span>{{ sup.company_name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Low Stock Checkbox Filter -->
                    <label class="inline-flex h-8.5 cursor-pointer items-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] text-zinc-700 shadow-xs hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                        <input
                            v-model="params.low_stock"
                            type="checkbox"
                            class="size-3.5 rounded border-zinc-300 text-zinc-900 dark:border-zinc-700"
                        />
                        <span>Low Stock</span>
                    </label>

                    <Button
                        v-if="params.search || params.category_id || params.supplier_id || params.low_stock"
                        variant="ghost"
                        size="dense"
                        class="text-xs text-muted-foreground"
                        @click="reset"
                    >
                        Clear filters
                    </Button>
                </div>

                <!-- Action Button -->
                <Link
                    :href="stock.planner.url()"
                    class="inline-flex h-8.5 shrink-0 items-center justify-center rounded-md bg-zinc-950 px-3.5 text-[0.8125rem] font-medium text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-zinc-950 dark:hover:bg-white"
                >
                    Stock Planner
                </Link>
            </div>

            <!-- Table Container -->
            <div class="min-h-[380px] overflow-x-auto">
                <table class="w-full min-w-[1140px] table-fixed border-separate border-spacing-0 text-left align-middle text-sm">
                    <thead>
                        <tr class="bg-zinc-50/50 text-[11px] font-semibold text-zinc-500 dark:bg-zinc-800/40 dark:text-zinc-400">
                            <th class="h-10 w-[50px] border-e border-b border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                <input
                                    v-model="selectAll"
                                    type="checkbox"
                                    class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700"
                                    @change="toggleSelectAll"
                                />
                            </th>
                            <th class="h-10 w-[270px] border-e border-b border-zinc-200 px-4 text-start align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <button
                                    type="button"
                                    class="-ms-2 inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                                >
                                    <span>Product Info</span>
                                    <ChevronsUpDown class="size-3 opacity-60" />
                                </button>
                            </th>
                            <th class="h-10 w-[190px] border-e border-b border-zinc-200 px-4 text-start align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <button
                                    type="button"
                                    class="-ms-2 inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                                    @click="toggleSort('quantity_on_hand')"
                                >
                                    <span>Stock Flow</span>
                                    <ChevronsUpDown class="size-3 opacity-60" />
                                </button>
                            </th>
                            <th class="h-10 w-[90px] border-e border-b border-zinc-200 px-4 text-center align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <span class="text-xs font-normal text-zinc-600 dark:text-zinc-400">Delta</span>
                            </th>
                            <th class="h-10 w-[90px] border-e border-b border-zinc-200 px-4 text-center align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <span class="text-xs font-normal text-zinc-600 dark:text-zinc-400">Price</span>
                            </th>
                            <th class="h-10 w-[110px] border-e border-b border-zinc-200 px-4 text-start align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <span class="text-xs font-normal text-zinc-600 dark:text-zinc-400">Category</span>
                            </th>
                            <th class="h-10 w-[160px] border-e border-b border-zinc-200 px-4 text-start align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <span class="text-xs font-normal text-zinc-600 dark:text-zinc-400">Supplier</span>
                            </th>
                            <th class="h-10 w-[130px] border-e border-b border-zinc-200 px-4 text-start align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <button
                                    type="button"
                                    class="-ms-2 inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                                    @click="toggleSort('updated_at')"
                                >
                                    <span>Updated</span>
                                    <ChevronsUpDown class="size-3 opacity-60" />
                                </button>
                            </th>
                            <th class="h-10 w-[60px] border-b border-zinc-200 px-4 text-center align-middle dark:border-zinc-800"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-if="rows.length === 0"
                        >
                            <td colspan="9" class="border-b border-zinc-200 py-12 text-center text-sm text-zinc-400 dark:border-zinc-800">
                                No products found matching the criteria.
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
                                    class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 dark:border-zinc-700"
                                    @change="toggleRowSelect(row.id)"
                                />
                            </td>

                            <!-- Product Info -->
                            <td class="border-e border-b border-zinc-200 px-4 py-3 align-middle dark:border-zinc-800">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-[40px] w-[50px] shrink-0 items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 p-1 dark:border-zinc-700 dark:bg-zinc-800/60">
                                        <img
                                            :src="getProductImage(row, idx)"
                                            :alt="row.product.name"
                                            class="size-full object-contain"
                                            loading="lazy"
                                        />
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-0.5">
                                        <Link
                                            :href="products.show.url(row.product_id)"
                                            class="truncate text-sm font-medium text-zinc-900 transition-colors hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                        >
                                            {{ row.product.name }}
                                        </Link>
                                        <span class="inline-flex items-center gap-1 text-xs">
                                            <span class="font-mono text-[11px] text-zinc-400 uppercase">SKU:</span>
                                            <span class="font-mono text-[11px] font-medium text-zinc-700 dark:text-zinc-300">
                                                {{ row.variant?.sku ?? row.product.sku ?? '—' }}
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Stock Flow -->
                            <td class="border-e border-b border-zinc-200 px-4 py-3 align-middle dark:border-zinc-800">
                                <div class="flex items-center gap-1.5">
                                    <div class="flex items-center gap-1.5" title="In Stock">
                                        <Layers class="size-3.5 shrink-0 text-zinc-400" />
                                        <span class="text-sm font-medium text-zinc-900 dark:text-white">
                                            {{ row.quantity_on_hand }}
                                        </span>
                                    </div>
                                    <div class="mx-0.5 h-3.5 w-px shrink-0 bg-zinc-200 dark:bg-zinc-700"></div>
                                    <div class="flex items-center gap-1.5" title="Available">
                                        <LogIn class="size-3.5 shrink-0 text-zinc-400" />
                                        <span class="text-sm font-medium text-zinc-900 dark:text-white">
                                            {{ available(row) }}
                                        </span>
                                    </div>
                                    <div class="mx-0.5 h-3.5 w-px shrink-0 bg-zinc-200 dark:bg-zinc-700"></div>
                                    <div class="flex items-center gap-1.5" title="Reserved">
                                        <LogOut class="size-3.5 shrink-0 text-zinc-400" />
                                        <span class="text-sm font-medium text-zinc-900 dark:text-white">
                                            {{ row.quantity_reserved }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Delta -->
                            <td class="border-e border-b border-zinc-200 px-4 py-3 text-center align-middle dark:border-zinc-800">
                                <span
                                    class="inline-flex h-6 items-center justify-center rounded-md px-2 text-xs font-medium"
                                    :class="
                                        delta(row).isPos
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'
                                            : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-400'
                                    "
                                >
                                    {{ delta(row).text }}
                                </span>
                            </td>

                            <!-- Price -->
                            <td class="border-e border-b border-zinc-200 px-4 py-3 text-center align-middle text-sm font-normal text-zinc-900 dark:border-zinc-800 dark:text-white">
                                {{ money(row.variant?.selling_price ?? row.product.selling_price ?? row.product.cost_price) }}
                            </td>

                            <!-- Category -->
                            <td class="border-e border-b border-zinc-200 px-4 py-3 text-start align-middle text-sm font-normal text-zinc-700 dark:border-zinc-800 dark:text-zinc-300">
                                {{ row.product.category?.name ?? '—' }}
                            </td>

                            <!-- Supplier -->
                            <td class="border-e border-b border-zinc-200 px-4 py-3 text-start align-middle dark:border-zinc-800">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="size-2 shrink-0 rounded-full"
                                        :style="{ backgroundColor: getSupplierColor(row.product.primary_supplier?.company_name) }"
                                    ></span>
                                    <span class="truncate text-sm font-normal text-zinc-800 dark:text-zinc-200">
                                        {{ row.product.primary_supplier?.company_name ?? '—' }}
                                    </span>
                                </div>
                            </td>

                            <!-- Updated -->
                            <td class="border-e border-b border-zinc-200 px-4 py-3 text-start align-middle text-sm font-normal text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">
                                {{ date(row.updated_at) }}
                            </td>

                            <!-- Actions -->
                            <td class="relative border-b border-zinc-200 px-3 py-3 text-center align-middle dark:border-zinc-800">
                                <div class="relative inline-block text-start" @click.stop>
                                    <button
                                        type="button"
                                        class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md p-0 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                        @click="toggleRowMenu(row.id, $event)"
                                    >
                                        <MoreVertical class="size-4" />
                                    </button>
                                    <div
                                        v-if="activeRowMenu === row.id"
                                        class="absolute right-0 top-full z-50 mt-1 w-36 overflow-hidden rounded-md border border-zinc-200 bg-white p-1 text-zinc-900 shadow-md shadow-black/10 dark:border-zinc-800 dark:bg-[#18181b] dark:text-zinc-100"
                                    >
                                        <div class="px-2 py-1.5 text-xs font-medium text-zinc-400 select-none dark:text-zinc-500">
                                            Actions
                                        </div>
                                        <div class="-mx-1 my-1 h-px bg-zinc-200 dark:bg-zinc-800"></div>
                                        <button
                                            v-if="can('inventory.adjust')"
                                            type="button"
                                            class="relative flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs text-zinc-700 outline-hidden transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800/80 dark:hover:text-white"
                                            @click="openAdjust(row)"
                                        >
                                            <SlidersHorizontal class="size-3.5 opacity-60" />
                                            <span>Adjust Stock</span>
                                        </button>
                                        <Link
                                            :href="products.edit.url(row.product_id)"
                                            class="relative flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs text-zinc-700 outline-hidden transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800/80 dark:hover:text-white"
                                        >
                                            <SquarePen class="size-3.5 opacity-60" />
                                            <span>Edit Product</span>
                                        </Link>
                                    </div>
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
                        <Select v-model.number="params.per_page" class="h-7 w-20 text-xs">
                            <option :value="10">10</option>
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </Select>
                    </div>

                    <!-- Page navigation -->
                    <div class="order-1 flex flex-col items-center justify-center gap-2.5 pt-2.5 sm:order-2 sm:flex-row sm:justify-end sm:pt-0">
                        <Pagination
                            :links="props.items.links"
                            :from="props.items.from"
                            :to="props.items.to"
                            :total="props.items.total"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Adjust Stock Drawer -->
        <Drawer
            :open="Boolean(adjusting)"
            title="Adjust stock"
            :description="
                adjusting
                    ? `${adjusting.product.name}${adjusting.variant ? ` · ${adjusting.variant.name}` : ''} — currently ${adjusting.quantity_on_hand} on hand`
                    : undefined
            "
            size="sm"
            @update:open="adjusting = null"
        >
            <div class="space-y-4">
                <FormField
                    label="Movement type"
                    :error="adjustForm.errors.type"
                    hint="The type decides the direction; quantity is always positive."
                >
                    <Select v-model="adjustForm.type">
                        <option
                            v-for="type in props.movementTypes"
                            :key="type"
                            :value="type"
                        >
                            {{ humanize(type) }}
                        </option>
                    </Select>
                </FormField>

                <FormField
                    label="Quantity"
                    :error="adjustForm.errors.quantity"
                    required
                >
                    <Input v-model.number="adjustForm.quantity" type="number" min="1" />
                </FormField>

                <FormField label="Reason" :error="adjustForm.errors.reason">
                    <Textarea
                        v-model="adjustForm.reason"
                        :rows="3"
                        placeholder="Recorded on the ledger row"
                    />
                </FormField>

                <p
                    v-if="firstOf('inventory', 'quantity')"
                    class="text-[11px] text-danger"
                >
                    {{ firstOf('inventory', 'quantity') }}
                </p>
            </div>

            <template #footer>
                <Button variant="outline" size="dense" @click="adjusting = null">
                    Cancel
                </Button>
                <Button
                    size="dense"
                    :disabled="adjustForm.processing"
                    @click="adjust"
                >
                    Record adjustment
                </Button>
            </template>
        </Drawer>
    </AppLayout>
</template>
