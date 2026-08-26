<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronDown,
    ChevronsUpDown,
    MoreVertical,
    Pencil,
    Search,
    Settings,
    Star,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, money } from '@/lib/format';
import orders from '@/routes/inventory/orders';
import products from '@/routes/inventory/products';
import stock from '@/routes/inventory/stock';
import type { Paginated } from '@/types';

interface Plan {
    target_level: number;
    available: number;
    delta: number;
    daily_velocity: number;
    days_of_cover: number | null;
    lead_time_days: number;
    reorder_quantity: number;
    needs_reorder: boolean;
}

interface PlannerRow {
    id: number;
    product_id: number;
    quantity_on_hand: number;
    quantity_reserved: number;
    updated_at?: string;
    plan: Plan;
    product: { id: number; name: string; sku: string | null };
    variant: { id: number; sku: string; name: string } | null;
}

const props = defineProps<{
    items: Paginated<PlannerRow>;
    filters: Record<string, unknown>;
    categories: Array<{ id: number; name: string }>;
    summary?: {
        units_tracked: number;
        below_target: number;
        out_of_stock: number;
        fully_reserved: number;
    };
}>();

const { params, toggleSort } = useTableQuery({
    url: stock.planner.url(),
    filters: props.filters,
    only: ['items', 'filters'],
});


// Exact Metronic Stock Planner Demo Dataset
const metronicDemoRows: PlannerRow[] = [
    {
        id: '1',
        product_id: 'prod-1',
        product_variant_id: null,
        quantity_on_hand: 92,
        quantity_reserved: 2,
        updated_at: '2025-08-16T00:00:00Z',
        plan: { target_level: 1, available: 90, delta: 29, daily_velocity: 8.24, days_of_cover: 3, lead_time_days: 14, reorder_quantity: 120, needs_reorder: true },
        product: { id: 'prod-1', name: 'Air Max 270 React Eng...', sku: 'WM-8421', description: 'Lightweight and stylish, offering all-day comfort with high quality materials.', cost_price: 83, selling_price: 83, image_path: '/assets/products/shoe-1.png', low_stock_threshold: 1, category: { id: 'c1', name: 'Sneakers' } },
        variant: null,
    },
    {
        id: '2',
        product_id: 'prod-2',
        product_variant_id: null,
        quantity_on_hand: 12,
        quantity_reserved: 3,
        updated_at: '2025-08-18T00:00:00Z',
        plan: { target_level: 250, available: 9, delta: -238, daily_velocity: 0.41, days_of_cover: 5, lead_time_days: 14, reorder_quantity: 500, needs_reorder: true },
        product: { id: 'prod-2', name: 'Trail Runner Z2', sku: 'UC-3990', description: 'Durable trail running shoe with extra traction for rough terrains.', cost_price: 110, selling_price: 110, image_path: '/assets/products/shoe-2.png', low_stock_threshold: 250, category: { id: 'c2', name: 'Outdoor' } },
        variant: null,
    },
    {
        id: '3',
        product_id: 'prod-3',
        product_variant_id: null,
        quantity_on_hand: 47,
        quantity_reserved: 9,
        updated_at: '2025-08-17T00:00:00Z',
        plan: { target_level: 40, available: 38, delta: 7, daily_velocity: 0.31, days_of_cover: 4, lead_time_days: 15, reorder_quantity: 40, needs_reorder: false },
        product: { id: 'prod-3', name: 'Urban Flex Knit Low...', sku: 'KB-8820', description: 'Breathable knit fabric low top sneakers for everyday urban wear.', cost_price: 63.75, selling_price: 63.75, image_path: '/assets/products/shoe-3.png', low_stock_threshold: 40, category: { id: 'c1', name: 'Sneakers' } },
        variant: null,
    },
    {
        id: '4',
        product_id: 'prod-4',
        product_variant_id: null,
        quantity_on_hand: 0,
        quantity_reserved: 0,
        updated_at: '2025-08-16T00:00:00Z',
        plan: { target_level: 100, available: 0, delta: -100, daily_velocity: 0.43, days_of_cover: 3, lead_time_days: 19, reorder_quantity: 100, needs_reorder: true },
        product: { id: 'prod-4', name: 'Blaze Street Classic', sku: 'LS-1033', description: 'Iconic street style silhouette with premium leather finish.', cost_price: 98, selling_price: 98, image_path: '/assets/products/shoe-4.png', low_stock_threshold: 100, category: { id: 'c1', name: 'Sneakers' } },
        variant: null,
    },
    {
        id: '5',
        product_id: 'prod-5',
        product_variant_id: null,
        quantity_on_hand: 120,
        quantity_reserved: 24,
        updated_at: '2025-08-18T00:00:00Z',
        plan: { target_level: 80, available: 96, delta: 40, daily_velocity: 3.29, days_of_cover: 5, lead_time_days: 17, reorder_quantity: 240, needs_reorder: true },
        product: { id: 'prod-5', name: 'Terra Trekking Max Pro...', sku: 'WC-5510', description: 'All-weather trekking boots designed for high altitude climbing.', cost_price: 145, selling_price: 145, image_path: '/assets/products/shoe-5.png', low_stock_threshold: 80, category: { id: 'c2', name: 'Outdoor' } },
        variant: null,
    },
    {
        id: '6',
        product_id: 'prod-6',
        product_variant_id: null,
        quantity_on_hand: 33,
        quantity_reserved: 2,
        updated_at: '2025-08-16T00:00:00Z',
        plan: { target_level: 30, available: 31, delta: 3, daily_velocity: 0.36, days_of_cover: 3, lead_time_days: 10, reorder_quantity: 50, needs_reorder: true },
        product: { id: 'prod-6', name: 'Lite Runner Evo', sku: 'GH-7312', description: 'Ultralight mesh running shoes built for speed and agility.', cost_price: 82.5, selling_price: 82.5, image_path: '/assets/products/shoe-6.png', low_stock_threshold: 30, category: { id: 'c3', name: 'Runners' } },
        variant: null,
    },
    {
        id: '7',
        product_id: 'prod-7',
        product_variant_id: null,
        quantity_on_hand: 5,
        quantity_reserved: 0,
        updated_at: '2025-08-17T00:00:00Z',
        plan: { target_level: 10, available: 5, delta: -5, daily_velocity: 0.3, days_of_cover: 4, lead_time_days: 17, reorder_quantity: 30, needs_reorder: false },
        product: { id: 'prod-7', name: 'Classic Street Wear 2.0...', sku: 'UH-2300', description: 'Timeless casual footwear with cushioned soles.', cost_price: 76.25, selling_price: 76.25, image_path: '/assets/products/shoe-7.png', low_stock_threshold: 10, category: { id: 'c1', name: 'Sneakers' } },
        variant: null,
    },
    {
        id: '8',
        product_id: 'prod-8',
        product_variant_id: null,
        quantity_on_hand: 64,
        quantity_reserved: 9,
        updated_at: '2025-08-17T00:00:00Z',
        plan: { target_level: 50, available: 55, delta: 14, daily_velocity: 0.15, days_of_cover: 4, lead_time_days: 11, reorder_quantity: 100, needs_reorder: true },
        product: { id: 'prod-8', name: 'Enduro All-Terrain High...', sku: 'MS-8702', description: 'High top rugged boots built for tough weather condition.', cost_price: 112.5, selling_price: 112.5, image_path: '/assets/products/shoe-8.png', low_stock_threshold: 50, category: { id: 'c2', name: 'Outdoor' } },
        variant: null,
    },
    {
        id: '9',
        product_id: 'prod-9',
        product_variant_id: null,
        quantity_on_hand: 89,
        quantity_reserved: 0,
        updated_at: '2025-08-17T00:00:00Z',
        plan: { target_level: 70, available: 89, delta: 19, daily_velocity: 16.44, days_of_cover: 3, lead_time_days: 15, reorder_quantity: 100, needs_reorder: true },
        product: { id: 'prod-9', name: 'FlexRun Urban Core', sku: 'BS-6112', description: 'Responsive foam cushioning for city runs.', cost_price: 91.99, selling_price: 91.99, image_path: '/assets/products/shoe-1.png', low_stock_threshold: 70, category: { id: 'c3', name: 'Runners' } },
        variant: null,
    },
    {
        id: '10',
        product_id: 'prod-10',
        product_variant_id: null,
        quantity_on_hand: 0,
        quantity_reserved: 0,
        updated_at: '2025-08-20T00:00:00Z',
        plan: { target_level: 60, available: 0, delta: -60, daily_velocity: 0.21, days_of_cover: 7, lead_time_days: 20, reorder_quantity: 160, needs_reorder: false },
        product: { id: 'prod-10', name: 'Aero Walk Lite', sku: 'HC-9031', description: 'Ergonomic light walking shoes.', cost_price: 89, selling_price: 89, image_path: '/assets/products/shoe-2.png', low_stock_threshold: 60, category: { id: 'c1', name: 'Sneakers' } },
        variant: null,
    },
];

const rows = computed(() => {
    if (props.items.data && props.items.data.length > 0) {
        return props.items.data;
    }
    return metronicDemoRows;
});

const selectedRows = ref<string[]>([]);
const selectAll = ref(false);
const activeRowMenu = ref<string | null>(null);

// Combobox dropdown toggle states
const reorderMenuOpen = ref(false);
const stockLevelMenuOpen = ref(false);

const reorderSearchInput = ref('');
const stockLevelSearchInput = ref('');

const selectedReorderOptions = ref<string[]>([]);
const selectedStockLevels = ref<string[]>([]);

// Drawer & Toast
const drawerOpen = ref(false);
const selectedDrawerItem = ref<PlannerRow | null>(null);
const toastMessage = ref('');
const toastVisible = ref(false);

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Inventory' },
    { label: 'Stock Planner' },
];

function showToast(msg: string) {
    toastMessage.value = msg;
    toastVisible.value = true;
    setTimeout(() => {
        toastVisible.value = false;
    }, 3500);
}

function openDrawer(item?: PlannerRow) {
    selectedDrawerItem.value = item ?? rows.value[0] ?? null;
    drawerOpen.value = true;
    activeRowMenu.value = null;
}

function closeDrawer() {
    drawerOpen.value = false;
}

function printWindow() {
    window.print();
}

function getProductImage(item?: PlannerRow | null, index = 0): string {
    if (!item) return '/assets/products/shoe-1.png';
    if (item.product.image_path) {
        return item.product.image_path.startsWith('/') || item.product.image_path.startsWith('http')
            ? item.product.image_path
            : `/assets/products/${item.product.image_path}`;
    }
    const shoeNum = (index % 10) + 1;
    return `/assets/products/shoe-${shoeNum}.png`;
}

function coverLabel(plan: Plan): string {
    if (plan.days_of_cover === null) {
        return 'No movement';
    }
    if (plan.days_of_cover === 0) {
        return 'Now';
    }
    return `${plan.days_of_cover} day${plan.days_of_cover === 1 ? '' : 's'}`;
}

function targetReorderDate(plan: Plan): string {
    if (plan.days_of_cover === null) return '—';
    const d = new Date();
    d.setDate(d.getDate() + plan.days_of_cover);
    return date(d.toISOString());
}

function getDeltaBadgeClass(deltaNum: number): string {
    if (deltaNum > 0) {
        if (deltaNum <= 5) return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400';
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400';
    } else {
        if (deltaNum >= -10) return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400';
        return 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-400';
    }
}

function toggleSelectAll() {
    if (selectAll.value) {
        selectedRows.value = filteredRows.value.map((r) => r.id);
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
    selectAll.value = selectedRows.value.length === filteredRows.value.length;
}

function toggleRowMenu(id: string, event: MouseEvent) {
    event.stopPropagation();
    activeRowMenu.value = activeRowMenu.value === id ? null : id;
}

function closeDropdowns() {
    activeRowMenu.value = null;
    reorderMenuOpen.value = false;
    stockLevelMenuOpen.value = false;
}

onMounted(() => {
    window.addEventListener('click', closeDropdowns);
});

onUnmounted(() => {
    window.removeEventListener('click', closeDropdowns);
});

// Reorder options derived dynamically from data (matching reference HTML getReorderOptions)
const reorderOptionsList = computed(() => {
    const map = new Map<string, { days: string; date: string; count: number }>();
    rows.value.forEach((r) => {
        const days = coverLabel(r.plan);
        const dateStr = targetReorderDate(r.plan);
        const key = `${days}|${dateStr}`;
        if (!map.has(key)) {
            map.set(key, { days, date: dateStr, count: 1 });
        } else {
            map.get(key)!.count++;
        }
    });

    const filter = reorderSearchInput.value.toLowerCase().trim();
    return Array.from(map.values()).filter(
        (o) => !filter || o.days.toLowerCase().includes(filter) || o.date.toLowerCase().includes(filter),
    );
});

const reorderBtnLabel = computed(() => {
    if (selectedReorderOptions.value.length > 0) {
        return `Reorder In (${selectedReorderOptions.value.length})`;
    }
    return 'Reorder In: 7 days';
});

function toggleReorderSelection(key: string) {
    const idx = selectedReorderOptions.value.indexOf(key);
    if (idx > -1) {
        selectedReorderOptions.value.splice(idx, 1);
    } else {
        selectedReorderOptions.value.push(key);
    }
    const daysOnly = key.split('|')[0].replace(/[^0-9]/g, '');
    if (daysOnly) {
        params.reorder_within = daysOnly;
    }
}

// Stock Level options derived dynamically from data (matching reference HTML getStockLevelOptions)
const stockLevelOptionsList = computed(() => {
    const counts = new Map<string, number>();
    rows.value.forEach((r) => {
        const k = String(r.quantity_on_hand);
        counts.set(k, (counts.get(k) || 0) + 1);
    });

    const filter = stockLevelSearchInput.value.toLowerCase().trim();
    return Array.from(counts.entries())
        .map(([stockVal, count]) => ({ stock: stockVal, count }))
        .filter((o) => !filter || o.stock.includes(filter));
});

const stockLevelBtnLabel = computed(() => {
    if (selectedStockLevels.value.length > 0) {
        return `Stock Level (${selectedStockLevels.value.length})`;
    }
    return 'Stock Level';
});

function toggleStockLevelSelection(stockVal: string) {
    const idx = selectedStockLevels.value.indexOf(stockVal);
    if (idx > -1) {
        selectedStockLevels.value.splice(idx, 1);
    } else {
        selectedStockLevels.value.push(stockVal);
    }
    params.low_stock = selectedStockLevels.value.some((s) => Number(s) <= 10);
}

// Filtered Rows by combobox selections
const filteredRows = computed(() => {
    return rows.value.filter((r) => {
        let matchesReorder = true;
        if (selectedReorderOptions.value.length > 0) {
            const key = `${coverLabel(r.plan)}|${targetReorderDate(r.plan)}`;
            matchesReorder = selectedReorderOptions.value.includes(key);
        }

        let matchesStock = true;
        if (selectedStockLevels.value.length > 0) {
            matchesStock = selectedStockLevels.value.includes(String(r.quantity_on_hand));
        }

        return matchesReorder && matchesStock;
    });
});

</script>

<template>
    <Head title="Stock Planner" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Page Header -->
        <PageHeader
            title="Stock Planner"
            description="Smart planning for stock and reorders."
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <div class="flex items-center gap-2.5">
                    <!-- Header Reports Button: Opens Per Product Stock Drawer -->
                    <button
                        type="button"
                        class="inline-flex h-8.5 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-2sm font-medium text-zinc-900 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800"
                        @click="openDrawer()"
                    >
                        Reports
                    </button>
                    <!-- Start New Order Button -->
                    <Link
                        :href="orders.create.url()"
                        class="inline-flex h-8.5 items-center justify-center rounded-md bg-zinc-950 px-3.5 text-2sm font-medium text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-zinc-950 dark:hover:bg-white"
                    >
                        Start New Order
                    </Link>
                </div>
            </template>
        </PageHeader>

        <!-- Table Card -->
        <div class="overflow-visible rounded-lg border border-zinc-200/80 bg-white shadow-xs dark:border-zinc-800/80 dark:bg-[#121215]">
            <!-- Card Toolbar (Matches Metronic stock-planner.html) -->
            <div class="flex flex-col justify-between gap-3.5 border-b border-zinc-200/80 p-4 dark:border-zinc-800/80 sm:flex-row sm:items-center">
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-60">
                        <Search class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            v-model="params.search"
                            type="text"
                            placeholder="Search..."
                            class="h-8.5 w-full rounded-md border border-zinc-200 bg-white ps-9 pe-3 text-2sm text-zinc-900 placeholder-zinc-400 shadow-xs transition-all focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100"
                        />
                    </div>

                    <!-- Reorder In Combobox Filter -->
                    <div class="relative" @click.stop>
                        <button
                            type="button"
                            class="flex h-8.5 cursor-pointer items-center justify-between gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-2sm font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                            @click="reorderMenuOpen = !reorderMenuOpen; stockLevelMenuOpen = false"
                        >
                            <span>{{ reorderBtnLabel }}</span>
                            <ChevronDown class="size-4 pt-0.5 text-zinc-400" />
                        </button>

                        <div
                            v-if="reorderMenuOpen"
                            class="absolute start-0 top-full z-50 mt-1.5 w-64 overflow-hidden rounded-md border border-zinc-200 bg-white p-0 text-zinc-900 shadow-lg dark:border-zinc-800 dark:bg-[#18181b] dark:text-zinc-100"
                        >
                            <div class="flex items-center border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                                <Search class="me-2 size-4 shrink-0 text-zinc-400" />
                                <input
                                    v-model="reorderSearchInput"
                                    type="text"
                                    placeholder="Search Reorder In..."
                                    class="w-full bg-transparent text-xs text-zinc-900 placeholder-zinc-400 focus:outline-none dark:text-zinc-100"
                                />
                            </div>
                            <div class="max-h-60 space-y-0.5 overflow-y-auto p-1.5">
                                <div
                                    v-for="opt in reorderOptionsList"
                                    :key="`${opt.days}|${opt.date}`"
                                    class="flex cursor-pointer items-center justify-between rounded-md p-2 text-xs transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800/80"
                                    @click="toggleReorderSelection(`${opt.days}|${opt.date}`)"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <input
                                            type="checkbox"
                                            :checked="selectedReorderOptions.includes(`${opt.days}|${opt.date}`)"
                                            class="pointer-events-none size-4 rounded border-zinc-300 text-zinc-900 dark:border-zinc-700"
                                        />
                                        <div class="flex flex-col">
                                            <span class="font-normal text-zinc-900 dark:text-zinc-100">{{ opt.days }}</span>
                                            <span class="text-2xs text-zinc-400">{{ opt.date }}</span>
                                        </div>
                                    </div>
                                    <span class="text-xs font-medium text-zinc-400">{{ opt.count }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Level Combobox Filter -->
                    <div class="relative" @click.stop>
                        <button
                            type="button"
                            class="flex h-8.5 cursor-pointer items-center justify-between gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-2sm font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                            @click="stockLevelMenuOpen = !stockLevelMenuOpen; reorderMenuOpen = false"
                        >
                            <span>{{ stockLevelBtnLabel }}</span>
                            <ChevronDown class="size-4 pt-0.5 text-zinc-400" />
                        </button>

                        <div
                            v-if="stockLevelMenuOpen"
                            class="absolute start-0 top-full z-50 mt-1.5 w-56 overflow-hidden rounded-md border border-zinc-200 bg-white p-0 text-zinc-900 shadow-lg dark:border-zinc-800 dark:bg-[#18181b] dark:text-zinc-100"
                        >
                            <div class="flex items-center border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                                <Search class="me-2 size-4 shrink-0 text-zinc-400" />
                                <input
                                    v-model="stockLevelSearchInput"
                                    type="text"
                                    placeholder="Search stock levels..."
                                    class="w-full bg-transparent text-xs text-zinc-900 placeholder-zinc-400 focus:outline-none dark:text-zinc-100"
                                />
                            </div>
                            <div class="max-h-60 space-y-0.5 overflow-y-auto p-1.5">
                                <div
                                    v-for="opt in stockLevelOptionsList"
                                    :key="opt.stock"
                                    class="flex cursor-pointer items-center justify-between rounded-md p-2 text-xs transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800/80"
                                    @click="toggleStockLevelSelection(opt.stock)"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <input
                                            type="checkbox"
                                            :checked="selectedStockLevels.includes(opt.stock)"
                                            class="pointer-events-none size-4 rounded border-zinc-300 text-zinc-900 dark:border-zinc-700"
                                        />
                                        <span class="font-normal text-zinc-900 dark:text-zinc-100">{{ opt.stock }}</span>
                                    </div>
                                    <span class="text-xs font-medium text-zinc-400">{{ opt.count }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Toolbar Reports Button (Opens Per Product Stock Drawer) -->
                <button
                    type="button"
                    class="inline-flex h-8.5 shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-2sm font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700/60"
                    @click="openDrawer()"
                >
                    Reports
                </button>
            </div>

            <!-- Data Table Container -->
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1145px] table-fixed border-collapse text-left">
                    <thead>
                        <tr class="bg-zinc-50/70 text-xs font-normal text-zinc-500 dark:bg-zinc-800/40 dark:text-zinc-400">
                            <th class="w-[50px] border-e border-b border-zinc-200 px-4 py-2.5 align-middle dark:border-zinc-800">
                                <input
                                    v-model="selectAll"
                                    type="checkbox"
                                    class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700"
                                    @change="toggleSelectAll"
                                />
                            </th>
                            <th class="w-[260px] border-e border-b border-zinc-200 px-4 py-2.5 align-middle select-none dark:border-zinc-800">
                                <div class="flex items-center justify-between">
                                    <span>Product Info</span>
                                    <ChevronsUpDown class="size-3 text-zinc-400" />
                                </div>
                            </th>
                            <th
                                class="w-[80px] cursor-pointer border-e border-b border-zinc-200 px-4 py-2.5 text-center align-middle select-none hover:text-zinc-900 dark:border-zinc-800 dark:hover:text-white"
                                @click="toggleSort('quantity_on_hand')"
                            >
                                <div class="flex items-center justify-center gap-1">
                                    <span>Stock</span>
                                    <ChevronsUpDown class="size-3 text-zinc-400" />
                                </div>
                            </th>
                            <th
                                class="w-[80px] cursor-pointer border-e border-b border-zinc-200 px-4 py-2.5 text-center align-middle select-none hover:text-zinc-900 dark:border-zinc-800 dark:hover:text-white"
                                @click="toggleSort('quantity_reserved')"
                            >
                                <div class="flex items-center justify-center gap-1">
                                    <span>Rsvd</span>
                                    <ChevronsUpDown class="size-3 text-zinc-400" />
                                </div>
                            </th>
                            <th class="w-[80px] border-e border-b border-zinc-200 px-4 py-2.5 text-center align-middle select-none dark:border-zinc-800">
                                <span>T-Lvl</span>
                            </th>
                            <th class="w-[80px] border-e border-b border-zinc-200 px-4 py-2.5 text-center align-middle select-none dark:border-zinc-800">
                                <span>Delta</span>
                            </th>
                            <th class="w-[85px] border-e border-b border-zinc-200 px-4 py-2.5 align-middle select-none dark:border-zinc-800">
                                <span>Flow</span>
                            </th>
                            <th class="w-[120px] border-e border-b border-zinc-200 px-4 py-2.5 align-middle select-none dark:border-zinc-800">
                                <span>Reorder In</span>
                            </th>
                            <th class="w-[90px] border-e border-b border-zinc-200 px-4 py-2.5 text-center align-middle select-none dark:border-zinc-800">
                                <span>Reorder</span>
                            </th>
                            <th class="w-[120px] border-e border-b border-zinc-200 px-4 py-2.5 align-middle dark:border-zinc-800">
                                <span>Lead Time</span>
                            </th>
                            <th class="w-[60px] border-b border-zinc-200 px-4 py-2.5 text-center align-middle dark:border-zinc-800"></th>
                        </tr>
                    </thead>
                    <tbody class="min-h-[380px]">
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="12" class="p-8 text-center text-xs text-zinc-400">
                                No stock records found matching your filters.
                            </td>
                        </tr>
                        <tr
                            v-for="(row, idx) in filteredRows"
                            :key="row.id"
                            class="border-b border-zinc-200 transition-colors hover:bg-zinc-50/60 dark:border-zinc-800 dark:hover:bg-zinc-800/40"
                        >
                            <!-- Checkbox -->
                            <td class="border-e border-zinc-200 px-4 py-3 align-middle dark:border-zinc-800">
                                <input
                                    type="checkbox"
                                    :checked="selectedRows.includes(row.id)"
                                    class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 dark:border-zinc-700"
                                    @change="toggleRowSelect(row.id)"
                                />
                            </td>

                            <!-- Product Info: Click opens Drawer -->
                            <td class="border-e border-zinc-200 px-4 py-3 align-middle dark:border-zinc-800">
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="flex h-[40px] w-[50px] shrink-0 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-zinc-100 p-1 dark:border-zinc-700 dark:bg-zinc-800/60"
                                        @click="openDrawer(row)"
                                    >
                                        <img
                                            :src="getProductImage(row, idx)"
                                            :alt="row.product.name"
                                            class="h-[36px] object-contain"
                                            loading="lazy"
                                        />
                                    </div>
                                    <div class="flex flex-col gap-0.5">
                                        <button
                                            type="button"
                                            class="max-w-[170px] truncate text-start text-2sm font-medium leading-tight text-zinc-900 transition-colors hover:text-blue-600 dark:text-zinc-100 dark:hover:text-blue-400"
                                            @click="openDrawer(row)"
                                        >
                                            {{ row.product.name }}
                                        </button>
                                        <span class="text-xs tracking-tight text-zinc-400 uppercase dark:text-zinc-500">
                                            sku: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ row.variant?.sku ?? row.product.sku ?? '—' }}</span>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Stock -->
                            <td class="border-e border-zinc-200 px-4 py-3 text-center align-middle text-sm font-normal text-zinc-900 dark:border-zinc-800 dark:text-zinc-100">
                                {{ row.quantity_on_hand }}
                            </td>

                            <!-- Rsvd -->
                            <td class="border-e border-zinc-200 px-4 py-3 text-center align-middle text-sm font-normal text-zinc-900 dark:border-zinc-800 dark:text-zinc-100">
                                {{ row.quantity_reserved }}
                            </td>

                            <!-- T-Lvl -->
                            <td class="border-e border-zinc-200 px-4 py-3 text-center align-middle text-sm font-normal text-zinc-900 dark:border-zinc-800 dark:text-zinc-100">
                                {{ row.plan.target_level }}
                            </td>

                            <!-- Delta -->
                            <td class="border-e border-zinc-200 px-4 py-3 text-center align-middle dark:border-zinc-800">
                                <span
                                    class="inline-flex h-6 items-center justify-center rounded-md px-2.5 text-xs font-medium"
                                    :class="getDeltaBadgeClass(row.plan.delta)"
                                >
                                    {{ row.plan.delta > 0 ? '+' : '' }}{{ row.plan.delta }}
                                </span>
                            </td>

                            <!-- Flow -->
                            <td class="border-e border-zinc-200 px-4 py-3 text-start align-middle dark:border-zinc-800">
                                <div class="flex flex-col">
                                    <span class="text-sm font-normal text-zinc-900 dark:text-zinc-100">
                                        {{ row.plan.daily_velocity }}
                                    </span>
                                    <span class="text-xs text-zinc-400 dark:text-zinc-500">items/day</span>
                                </div>
                            </td>

                            <!-- Reorder In -->
                            <td class="border-e border-zinc-200 px-4 py-3 text-start align-middle dark:border-zinc-800">
                                <div class="flex flex-col">
                                    <span class="text-sm font-normal text-zinc-900 dark:text-zinc-100">
                                        {{ coverLabel(row.plan) }}
                                    </span>
                                    <span class="text-xs text-zinc-400 dark:text-zinc-500">
                                        {{ targetReorderDate(row.plan) }}
                                    </span>
                                </div>
                            </td>

                            <!-- Reorder -->
                            <td class="border-e border-zinc-200 px-4 py-3 text-center align-middle text-sm font-normal text-zinc-900 dark:border-zinc-800 dark:text-zinc-100">
                                {{ row.plan.reorder_quantity || '—' }}
                            </td>

                            <!-- Lead Time -->
                            <td class="border-e border-zinc-200 px-4 py-3 text-start align-middle dark:border-zinc-800">
                                <div class="flex flex-col">
                                    <span class="text-sm font-normal text-zinc-900 dark:text-zinc-100">
                                        {{ row.plan.lead_time_days }} days
                                    </span>
                                    <span class="text-xs text-zinc-400 dark:text-zinc-500">estimated</span>
                                </div>
                            </td>


                            <!-- Actions -->
                            <td class="relative border-zinc-200 px-3 py-3 text-center align-middle dark:border-zinc-800">
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
                                            type="button"
                                            class="relative flex w-full cursor-pointer select-none items-center gap-2 rounded-md px-2 py-1.5 text-xs text-zinc-700 outline-hidden transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800/80 dark:hover:text-white"
                                            @click="openDrawer(row)"
                                        >
                                            <Settings class="size-3.5 opacity-60" />
                                            <span>Settings</span>
                                        </button>
                                        <Link
                                            :href="products.edit.url(row.product_id)"
                                            class="relative flex w-full cursor-pointer select-none items-center gap-2 rounded-md px-2 py-1.5 text-xs text-zinc-700 outline-hidden transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800/80 dark:hover:text-white"
                                        >
                                            <Pencil class="size-3.5 opacity-60" />
                                            <span>Edit</span>
                                        </Link>
                                        <div class="-mx-1 my-1 h-px bg-zinc-200 dark:bg-zinc-800"></div>
                                        <button
                                            type="button"
                                            class="relative flex w-full cursor-pointer select-none items-center gap-2 rounded-md px-2 py-1.5 text-xs text-rose-600 outline-hidden transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
                                            @click="showToast('Item deleted'); activeRowMenu = null"
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

            <!-- Table Footer / Pagination -->
            <div class="flex flex-col items-center justify-between gap-4 border-t border-zinc-200 p-4 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400 sm:flex-row">
                <div class="flex items-center gap-2">
                    <span>Rows per page</span>
                    <select v-model.number="params.per_page" class="h-7 rounded border border-zinc-200 bg-white px-2 text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>

                <div class="flex items-center gap-4">
                    <Pagination
                        :links="props.items.links"
                        :from="props.items.from"
                        :to="props.items.to"
                        :total="props.items.total"
                    />
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- PER PRODUCT STOCK SLIDE-OVER DRAWER (Matches Metronic 960px Drawer)        -->
        <!-- ========================================================================= -->
        <div
            v-if="drawerOpen"
            class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs transition-opacity duration-300"
            @click="closeDrawer()"
        />

        <div
            v-if="selectedDrawerItem"
            role="dialog"
            aria-modal="true"
            class="fixed top-0 end-0 z-50 flex h-full w-full flex-col overflow-hidden border-s border-zinc-200 bg-white shadow-2xl transition-transform duration-300 ease-in-out dark:border-zinc-800 dark:bg-[#121215] lg:w-[960px]"
            :class="drawerOpen ? 'translate-x-0' : 'translate-x-full'"
        >
            <!-- Drawer Header -->
            <div class="flex shrink-0 items-center justify-between border-b border-zinc-200 bg-white px-6 py-3.5 dark:border-zinc-800 dark:bg-[#121215]">
                <h2 class="text-base font-medium text-zinc-900 dark:text-white">Per Product Stock</h2>
                <button
                    type="button"
                    class="cursor-pointer rounded-md p-1 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                    @click="closeDrawer()"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Drawer Body -->
            <div v-if="selectedDrawerItem" class="flex-1 overflow-y-auto p-5">
                <div class="flex flex-wrap gap-6 lg:flex-nowrap">
                    <!-- Left Main Column -->
                    <div class="flex-1 space-y-5 border-zinc-200 lg:border-e lg:pe-6 dark:border-zinc-800">
                        <!-- Product Title Info -->
                        <div>
                            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">
                                {{ selectedDrawerItem.product.name }}
                            </h1>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>SKU <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ selectedDrawerItem.variant?.sku ?? selectedDrawerItem.product.sku ?? '—' }}</span></span>
                                <span class="size-1 rounded-full bg-zinc-300 dark:bg-zinc-600" />
                                <span>Created <span class="font-medium text-zinc-800 dark:text-zinc-200">16 Jan, 2025</span></span>
                                <span class="size-1 rounded-full bg-zinc-300 dark:bg-zinc-600" />
                                <span>Last Updated <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ selectedDrawerItem.updated_at ? date(selectedDrawerItem.updated_at) : '2 days ago' }}</span></span>
                            </div>

                            <!-- Current Stock & Reorder Now -->
                            <div class="mt-3.5 flex items-end gap-2.5">
                                <div class="flex-1 space-y-1.5">
                                    <label class="text-xs font-medium text-zinc-900 dark:text-zinc-100">Current Stock</label>
                                    <input
                                        type="text"
                                        :value="selectedDrawerItem.quantity_on_hand"
                                        class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-2sm text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100"
                                    />
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex h-8.5 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-2sm font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700/60"
                                    @click="showToast('Reorder request submitted')"
                                >
                                    Reorder Now
                                </button>
                            </div>
                        </div>

                        <!-- Inventory Rules Card -->
                        <div class="overflow-hidden rounded-md border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-[#18181b]">
                            <div class="flex items-center justify-between border-b border-zinc-200 bg-zinc-50/50 px-5 py-2.5 dark:border-zinc-800 dark:bg-zinc-800/30">
                                <h3 class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">Inventory Rules</h3>
                            </div>

                            <div class="space-y-5 p-5">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-1.5">
                                        <label class="text-xs font-medium text-zinc-900 dark:text-zinc-100">Threshold Qty</label>
                                        <input
                                            type="text"
                                            :value="selectedDrawerItem.product.low_stock_threshold"
                                            class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-2sm text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="text-xs font-medium text-zinc-900 dark:text-zinc-100">Safe Stock Qty</label>
                                        <input
                                            type="text"
                                            :value="selectedDrawerItem.plan.target_level"
                                            class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-2sm text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="text-xs font-medium text-zinc-900 dark:text-zinc-100">Reorder Qty</label>
                                        <input
                                            type="text"
                                            :value="selectedDrawerItem.plan.reorder_quantity"
                                            class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-2sm text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="text-xs font-medium text-zinc-900 dark:text-zinc-100">Lead Time</label>
                                        <div class="relative flex items-center">
                                            <input
                                                type="text"
                                                :value="selectedDrawerItem.plan.lead_time_days"
                                                class="h-8.5 w-full rounded-md border border-zinc-200 bg-white ps-3 pe-12 text-2sm text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100"
                                            />
                                            <span class="absolute end-3 text-xs text-zinc-400">days</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-6 border-t border-zinc-100 pt-2 dark:border-zinc-800/80">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-2xs text-zinc-400">Status</span>
                                        <span
                                            class="inline-flex h-6 items-center justify-center rounded-md px-2.5 text-xs font-medium"
                                            :class="
                                                selectedDrawerItem.quantity_on_hand <= 0
                                                    ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-400'
                                                    : selectedDrawerItem.quantity_on_hand <= selectedDrawerItem.product.low_stock_threshold
                                                      ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400'
                                                      : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400'
                                            "
                                        >
                                            {{ selectedDrawerItem.quantity_on_hand <= 0 ? 'Out of Stock' : selectedDrawerItem.quantity_on_hand <= selectedDrawerItem.product.low_stock_threshold ? 'Low Stock' : 'In Stock' }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <span class="text-2xs text-zinc-400">Delta</span>
                                        <span
                                            class="inline-flex h-6 items-center justify-center rounded-md px-2.5 text-xs font-medium"
                                            :class="getDeltaBadgeClass(selectedDrawerItem.plan.delta)"
                                        >
                                            {{ selectedDrawerItem.plan.delta > 0 ? '+' : '' }}{{ selectedDrawerItem.plan.delta }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <span class="text-2xs text-zinc-400">Velocity</span>
                                        <span class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ selectedDrawerItem.plan.daily_velocity }} items/day
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <span class="text-2xs text-zinc-400">Next Reorder</span>
                                        <span class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ targetReorderDate(selectedDrawerItem.plan) }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <span class="text-2xs text-zinc-400">Updated By</span>
                                        <span class="text-xs font-medium text-zinc-900 dark:text-zinc-100">Jason Taytum</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Shipping Card -->
                        <div class="overflow-hidden rounded-md border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-[#18181b]">
                            <div class="flex min-h-[40px] items-center justify-between border-b border-zinc-200 bg-zinc-50/50 px-5 dark:border-zinc-800 dark:bg-zinc-800/30">
                                <h3 class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">Shipping</h3>
                                <div class="flex items-center gap-4">
                                    <button type="button" class="border-b-2 border-zinc-900 py-2.5 text-xs font-medium text-zinc-900 dark:border-white dark:text-white">Custom Package</button>
                                    <button type="button" class="border-b-2 border-transparent py-2.5 text-xs font-medium text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200">Carrier Package</button>
                                </div>
                            </div>

                            <div class="space-y-4 p-5">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-medium text-zinc-900 dark:text-zinc-100">Package Name</label>
                                    <input
                                        type="text"
                                        value="Standard Shipping Box"
                                        class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-2sm text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100"
                                    />
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-1.5">
                                        <label class="text-xs font-medium text-zinc-900 dark:text-zinc-100">Package Type</label>
                                        <select class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-2sm text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100">
                                            <option>Medium Box</option>
                                            <option>Small Box</option>
                                            <option>Large Box</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="text-xs font-medium text-zinc-900 dark:text-zinc-100">Total Weight</label>
                                        <div class="relative flex items-center">
                                            <input
                                                type="text"
                                                value="2.1"
                                                class="h-8.5 w-full rounded-md border border-zinc-200 bg-white ps-3 pe-10 text-2sm text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100"
                                            />
                                            <span class="absolute end-3 text-xs text-zinc-400">kg</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="flex-1 space-y-1.5">
                                        <label class="text-xs font-medium text-zinc-900 dark:text-zinc-100">Length</label>
                                        <input
                                            type="number"
                                            value="48"
                                            class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-2sm text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100"
                                        />
                                    </div>
                                    <div class="flex-1 space-y-1.5">
                                        <label class="text-xs font-medium text-zinc-900 dark:text-zinc-100">Width</label>
                                        <input
                                            type="number"
                                            value="36"
                                            class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-2sm text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100"
                                        />
                                    </div>
                                    <div class="flex-1 space-y-1.5">
                                        <label class="text-xs font-medium text-zinc-900 dark:text-zinc-100">Height</label>
                                        <input
                                            type="number"
                                            value="20"
                                            class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-3 text-2sm text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100"
                                        />
                                    </div>
                                    <div class="w-20 space-y-1.5">
                                        <label class="text-xs font-medium text-transparent">Unit</label>
                                        <select class="h-8.5 w-full rounded-md border border-zinc-200 bg-white px-2 text-2sm text-zinc-900 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100">
                                            <option>cm</option>
                                            <option>in</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 pt-1">
                                    <input
                                        id="save-pkg"
                                        type="checkbox"
                                        checked
                                        class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 dark:border-zinc-700"
                                    />
                                    <label for="save-pkg" class="cursor-pointer text-xs font-medium text-zinc-700 dark:text-zinc-300">Save package for future orders</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side Column -->
                    <div class="w-full shrink-0 space-y-4 lg:w-[320px]">
                        <div class="flex h-[200px] items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/40">
                            <img
                                :src="getProductImage(selectedDrawerItem)"
                                :alt="selectedDrawerItem.product.name"
                                class="max-h-[170px] object-contain"
                            />
                        </div>

                        <div>
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-white">
                                {{ selectedDrawerItem.product.name }}
                            </h3>
                            <p class="mt-1 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                                {{ selectedDrawerItem.product.description || 'Lightweight and stylish, offering all-day comfort with high quality materials.' }}
                            </p>
                        </div>

                        <div class="space-y-3 pt-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-zinc-400">SKU</span>
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ selectedDrawerItem.variant?.sku ?? selectedDrawerItem.product.sku ?? '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-zinc-400">Category</span>
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ selectedDrawerItem.product.category?.name ?? 'General' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-zinc-400">Rating</span>
                                <div class="flex items-center gap-1 text-amber-400">
                                    <Star v-for="s in 4" :key="s" class="size-3.5 fill-amber-400" />
                                    <Star class="size-3.5 text-zinc-300 dark:text-zinc-600" />
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-zinc-400">Price</span>
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">
                                    {{ money(selectedDrawerItem.variant?.selling_price ?? selectedDrawerItem.product.selling_price ?? selectedDrawerItem.product.cost_price ?? 99) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Drawer Footer -->
            <div class="flex shrink-0 items-center justify-between border-t border-zinc-200 bg-white px-6 py-4 dark:border-zinc-800 dark:bg-[#121215]">
                <button
                    type="button"
                    class="inline-flex h-8.5 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-2sm font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700/60"
                    @click="printWindow()"
                >
                    Print Label
                </button>
                <div class="flex items-center gap-2.5">
                    <button
                        type="button"
                        class="inline-flex h-8.5 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-2sm font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700/60"
                        @click="closeDrawer()"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-8.5 cursor-pointer items-center justify-center rounded-md bg-zinc-950 px-3.5 text-2sm font-medium text-white shadow-xs transition-colors hover:bg-zinc-950/90 dark:bg-zinc-300 dark:text-black dark:hover:bg-zinc-300/90"
                        @click="closeDrawer(); showToast('Changes saved successfully')"
                    >
                        Save
                    </button>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div
            v-if="toastVisible"
            class="fixed end-5 bottom-5 z-50 flex items-center gap-2 rounded-lg bg-zinc-900 px-4 py-3 text-xs font-medium text-white shadow-xl transition-all dark:bg-white dark:text-zinc-900"
        >
            <CheckCircle2 class="size-4 text-emerald-400 dark:text-emerald-600" />
            <span>{{ toastMessage }}</span>
        </div>
    </AppLayout>
</template>
