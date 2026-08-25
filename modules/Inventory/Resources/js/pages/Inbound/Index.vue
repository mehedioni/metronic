<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Calendar as CalendarIcon,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    Circle,
    EllipsisVertical,
    MapPin,
    Pencil,
    PlusIcon,
    Search,
    Settings,
    Trash2,
    Truck,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { FormField, FormSection } from '@/components/form';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useCsvExport } from '@/composables/useCsvExport';
import { usePageErrors } from '@/composables/usePageErrors';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, money, number } from '@/lib/format';
import { humanize } from '@/lib/status';
import inbound from '@/routes/inventory/inbound';
import stockRoutes from '@/routes/inventory/stock';
import type { Paginated } from '@/types';

interface ReceiptRow {
    id: number;
    reference_number: string;
    source: string;
    status: string;
    received_date: string | null;
    created_at?: string | null;
    items_count: number;
    items_sum_quantity: number | null;
    supplier: { id: number; company_name: string } | null;
    received_by: { id: number; name: string } | null;
    items?: Array<{
        id: string;
        quantity: number;
        unit_cost: number;
        product?: { name: string; sku: string };
    }>;
}

interface ProductOption {
    id: number;
    name: string;
    sku: string | null;
    variants: Array<{ id: number; sku: string; name: string }>;
}

const props = defineProps<{
    receipts: Paginated<ReceiptRow>;
    filters: Record<string, unknown>;
    options: {
        suppliers?: Array<{ id: number; company_name: string }>;
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

// Form for creating new receipt
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

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Inventory' },
    { label: 'Inbound Stock' },
];

/**
 * Variants of the product chosen on a line, for its variant select.
 *
 * The line holds the id as a string, because that is what the select binds;
 * the option carries the numeric key, so the two are compared as strings.
 */
function variantsFor(productId: string) {
    return (
        props.options.products?.find(
            (product) => String(product.id) === productId,
        )?.variants ?? []
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

function openTrackingDrawer(row: ReceiptRow) {
    activeTrackingRow.value = row;
    trackingDrawerOpen.value = true;
    activeRowAction.value = null;
}

function closeTrackingDrawer() {
    trackingDrawerOpen.value = false;
    activeTrackingRow.value = null;
}

function getStatusBadgeClass(status: string) {
    const s = status.toLowerCase();
    if (s === 'received' || s === 'completed' || s === 'posted') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-400';
    }
    if (s === 'processing' || s === 'inbound' || s === 'shipped') {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-950/80 dark:text-blue-400';
    }
    if (s === 'draft' || s === 'pending') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-400';
    }
    if (s === 'cancelled' || s === 'rejected') {
        return 'bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-400';
    }
    return 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200';
}
</script>

<template>
    <Head title="Inbound Stock" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Metronic Sub-Nav Tabs -->
        <div class="-mx-4 -mt-4 mb-6 border-b border-dashed border-zinc-200/80 bg-white px-4 pt-4 dark:border-zinc-800/80 dark:bg-[#121215] lg:-mx-8 lg:px-8">
            <div class="flex items-center gap-6 text-xs font-semibold">
                <Link
                    :href="stockRoutes.index.url()"
                    class="pb-3 text-zinc-500 transition-colors hover:text-zinc-900 dark:hover:text-white"
                >
                    All Stock
                </Link>
                <Link
                    :href="stockRoutes.index.url()"
                    class="pb-3 text-zinc-500 transition-colors hover:text-zinc-900 dark:hover:text-white"
                >
                    Current Stock
                </Link>
                <Link
                    :href="inbound.index.url()"
                    class="border-b-2 border-blue-600 pb-3 text-blue-600 dark:border-blue-400 dark:text-blue-400"
                >
                    Inbound Stock
                </Link>
                <Link
                    :href="stockRoutes.index.url()"
                    class="pb-3 text-zinc-500 transition-colors hover:text-zinc-900 dark:hover:text-white"
                >
                    Outbound Stock
                </Link>
            </div>
        </div>

        <!-- TABLE CARD -->
        <div class="rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-[#121215]">
            <!-- Card Toolbar -->
            <div class="flex flex-col items-stretch justify-between gap-3 border-b border-zinc-200 p-4 sm:flex-row sm:items-center dark:border-zinc-800">
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-48 lg:w-56">
                        <Search class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            v-model="params.search"
                            type="text"
                            placeholder="Search..."
                            class="h-[34px] w-full rounded-md border border-zinc-200 bg-white ps-9 pe-3 text-xs text-zinc-900 placeholder-zinc-400 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                        />
                    </div>

                    <!-- Date Range Calendar Dropdown -->
                    <div class="relative" @click.stop>
                        <button
                            type="button"
                            class="inline-flex h-[34px] cursor-pointer items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                            @click="dateMenuOpen = !dateMenuOpen; statusMenuOpen = false; supplierMenuOpen = false"
                        >
                            <span>{{ dateBtnLabel }}</span>
                            <ChevronDown class="ml-0.5 size-4 opacity-60" />
                        </button>
                        <!-- Dual Month Range Picker Popover -->
                        <div
                            v-if="dateMenuOpen"
                            class="absolute start-0 top-full z-50 mt-1.5 w-[320px] rounded-lg border border-zinc-200 bg-white p-4 text-zinc-900 shadow-xl shadow-black/10 dark:border-zinc-800 dark:bg-[#18181b] dark:text-zinc-100 sm:w-[580px]"
                        >
                            <div class="mb-3 flex items-center justify-between border-b border-zinc-100 pb-2 dark:border-zinc-800">
                                <div class="flex items-center gap-1.5">
                                    <CalendarIcon class="size-4 text-zinc-500" />
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">Select Date Range</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="flex size-7 items-center justify-center rounded-md text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                                        @click="changeCalendarMonth(-1)"
                                    >
                                        <ChevronLeft class="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="flex size-7 items-center justify-center rounded-md text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                                        @click="changeCalendarMonth(1)"
                                    >
                                        <ChevronRight class="size-4" />
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <!-- Month 1 -->
                                <div>
                                    <div class="mb-2.5 text-center text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ monthNames[month1Grid.month] }} {{ month1Grid.year }}
                                    </div>
                                    <div class="mb-1 grid grid-cols-7 gap-y-1 text-center">
                                        <span class="flex size-8 items-center justify-center text-[11px] font-medium text-zinc-400">Su</span>
                                        <span class="flex size-8 items-center justify-center text-[11px] font-medium text-zinc-400">Mo</span>
                                        <span class="flex size-8 items-center justify-center text-[11px] font-medium text-zinc-400">Tu</span>
                                        <span class="flex size-8 items-center justify-center text-[11px] font-medium text-zinc-400">We</span>
                                        <span class="flex size-8 items-center justify-center text-[11px] font-medium text-zinc-400">Th</span>
                                        <span class="flex size-8 items-center justify-center text-[11px] font-medium text-zinc-400">Fr</span>
                                        <span class="flex size-8 items-center justify-center text-[11px] font-medium text-zinc-400">Sa</span>
                                    </div>
                                    <div class="grid grid-cols-7 gap-y-1 text-center">
                                        <div
                                            v-for="d in month1Grid.leadingDays"
                                            :key="'m1-lead-' + d"
                                            class="flex size-8 select-none items-center justify-center text-xs text-zinc-300 dark:text-zinc-700"
                                        >
                                            {{ d }}
                                        </div>
                                        <button
                                            v-for="d in month1Grid.monthDays"
                                            :key="'m1-day-' + d"
                                            type="button"
                                            :class="getDayCellClass(month1Grid.year, month1Grid.month, d)"
                                            @click="selectCalendarDate(month1Grid.year, month1Grid.month, d)"
                                        >
                                            {{ d }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Month 2 -->
                                <div class="hidden sm:block">
                                    <div class="mb-2.5 text-center text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ monthNames[month2Grid.month] }} {{ month2Grid.year }}
                                    </div>
                                    <div class="mb-1 grid grid-cols-7 gap-y-1 text-center">
                                        <span class="flex size-8 items-center justify-center text-[11px] font-medium text-zinc-400">Su</span>
                                        <span class="flex size-8 items-center justify-center text-[11px] font-medium text-zinc-400">Mo</span>
                                        <span class="flex size-8 items-center justify-center text-[11px] font-medium text-zinc-400">Tu</span>
                                        <span class="flex size-8 items-center justify-center text-[11px] font-medium text-zinc-400">We</span>
                                        <span class="flex size-8 items-center justify-center text-[11px] font-medium text-zinc-400">Th</span>
                                        <span class="flex size-8 items-center justify-center text-[11px] font-medium text-zinc-400">Fr</span>
                                        <span class="flex size-8 items-center justify-center text-[11px] font-medium text-zinc-400">Sa</span>
                                    </div>
                                    <div class="grid grid-cols-7 gap-y-1 text-center">
                                        <div
                                            v-for="d in month2Grid.leadingDays"
                                            :key="'m2-lead-' + d"
                                            class="flex size-8 select-none items-center justify-center text-xs text-zinc-300 dark:text-zinc-700"
                                        >
                                            {{ d }}
                                        </div>
                                        <button
                                            v-for="d in month2Grid.monthDays"
                                            :key="'m2-day-' + d"
                                            type="button"
                                            :class="getDayCellClass(month2Grid.year, month2Grid.month, d)"
                                            @click="selectCalendarDate(month2Grid.year, month2Grid.month, d)"
                                        >
                                            {{ d }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        class="rounded-md bg-zinc-100 px-2.5 py-1 text-[11px] font-medium text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                                        @click="applyPresetRange('today')"
                                    >
                                        Today
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md bg-zinc-100 px-2.5 py-1 text-[11px] font-medium text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                                        @click="applyPresetRange('last30')"
                                    >
                                        Last 30 Days
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md bg-zinc-100 px-2.5 py-1 text-[11px] font-medium text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                                        @click="applyPresetRange('thisYear')"
                                    >
                                        This Year
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-md bg-zinc-900 px-3 py-1 text-xs font-semibold text-white shadow-xs hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                                    @click="dateMenuOpen = false"
                                >
                                    Apply
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Status Filter Dropdown -->
                    <div class="relative" @click.stop>
                        <button
                            type="button"
                            class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                            @click="statusMenuOpen = !statusMenuOpen; dateMenuOpen = false; supplierMenuOpen = false"
                        >
                            <span>{{ statusBtnLabel }}</span>
                            <ChevronDown class="ml-0.5 size-4 opacity-60" />
                        </button>
                        <div
                            v-if="statusMenuOpen"
                            class="absolute start-0 top-full z-50 mt-1.5 w-56 overflow-hidden rounded-lg border border-zinc-200 bg-white text-zinc-900 shadow-xl shadow-black/10 dark:border-zinc-800 dark:bg-[#18181b] dark:text-zinc-100"
                        >
                            <div class="flex items-center border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                                <Search class="me-2 size-3.5 shrink-0 text-zinc-400" />
                                <input
                                    v-model="statusSearchInput"
                                    type="text"
                                    class="w-full bg-transparent text-xs text-zinc-900 placeholder:text-zinc-400 focus:outline-none dark:text-zinc-100"
                                    placeholder="Search status..."
                                />
                            </div>
                            <div class="max-h-60 space-y-0.5 overflow-y-auto p-1.5">
                                <label
                                    v-for="st in (props.options.statuses || ['received', 'processing', 'draft', 'cancelled'])"
                                    :key="st"
                                    class="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-xs transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800/70"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="selectedStatuses.includes(st)"
                                        class="size-4 rounded border-zinc-300 text-zinc-900 dark:border-zinc-700"
                                        @change="toggleStatusSelection(st)"
                                    />
                                    <span class="flex grow items-center justify-between">
                                        <span
                                            class="inline-flex h-5 items-center justify-center rounded px-2 text-[11px] font-medium"
                                            :class="getStatusBadgeClass(st)"
                                        >
                                            {{ humanize(st) }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Supplier Filter Dropdown -->
                    <div class="relative" @click.stop>
                        <button
                            type="button"
                            class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                            @click="supplierMenuOpen = !supplierMenuOpen; dateMenuOpen = false; statusMenuOpen = false"
                        >
                            <span>{{ supplierBtnLabel }}</span>
                            <ChevronDown class="ml-0.5 size-4 opacity-60" />
                        </button>
                        <div
                            v-if="supplierMenuOpen"
                            class="absolute start-0 top-full z-50 mt-1.5 w-56 overflow-hidden rounded-lg border border-zinc-200 bg-white text-zinc-900 shadow-xl dark:border-zinc-800 dark:bg-[#18181b] dark:text-zinc-100"
                        >
                            <div class="flex items-center border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                                <Search class="me-2 size-3.5 shrink-0 text-zinc-400" />
                                <input
                                    v-model="supplierSearchInput"
                                    type="text"
                                    class="w-full bg-transparent text-xs text-zinc-900 placeholder:text-zinc-400 focus:outline-none dark:text-zinc-100"
                                    placeholder="Search supplier..."
                                />
                            </div>
                            <div class="max-h-60 space-y-0.5 overflow-y-auto p-1.5">
                                <label
                                    v-for="sup in (props.options.suppliers || [])"
                                    :key="sup.id"
                                    class="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-xs transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800/70"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="selectedSuppliers.includes(sup.id)"
                                        class="size-4 rounded border-zinc-300 text-zinc-900 dark:border-zinc-700"
                                        @change="toggleSupplierSelection(sup.id)"
                                    />
                                    <span class="text-xs text-zinc-800 dark:text-zinc-200">{{ sup.company_name }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Toolbar Action Buttons (Matches Metronic inbound-stock.html) -->
                <Link
                    :href="stockRoutes.planner.url()"
                    class="inline-flex h-8.5 shrink-0 items-center justify-center rounded-md bg-zinc-950 px-3.5 text-[0.8125rem] font-medium text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-zinc-950 dark:hover:bg-white"
                >
                    <span>Stock Planner</span>
                </Link>
            </div>

            <!-- Table Container -->
            <div class="min-h-[380px] overflow-x-auto">
                <table class="w-full min-w-[1130px] table-fixed border-separate border-spacing-0 text-left text-sm align-middle caption-bottom">
                    <thead>
                        <tr class="bg-zinc-50/50 text-[11px] font-semibold text-zinc-500 dark:bg-zinc-800/40 dark:text-zinc-400">
                            <th class="h-10 w-[50px] border-e border-b border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                <input
                                    v-model="selectAll"
                                    type="checkbox"
                                    class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700"
                                />
                            </th>
                            <th class="h-10 w-[200px] border-e border-b border-zinc-200 px-4 text-start align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <button
                                    type="button"
                                    class="group -ms-2 flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                                    @click="toggleSort('reference_number')"
                                >
                                    <span>Product</span>
                                    <ChevronsUpDown class="size-3 opacity-60" />
                                </button>
                            </th>
                            <th class="h-10 w-[120px] border-e border-b border-zinc-200 px-4 text-start align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <button
                                    type="button"
                                    class="group -ms-2 flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                                    @click="toggleSort('received_date')"
                                >
                                    <span>Order Date</span>
                                    <ChevronsUpDown class="size-3 opacity-60" />
                                </button>
                            </th>
                            <th class="h-10 w-[80px] border-e border-b border-zinc-200 px-4 text-center align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <span class="w-full text-center text-xs font-normal text-zinc-600 dark:text-zinc-400">QTY</span>
                            </th>
                            <th class="h-10 w-[90px] border-e border-b border-zinc-200 px-4 text-center align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <span class="w-full text-center text-xs font-normal text-zinc-600 dark:text-zinc-400">Stock</span>
                            </th>
                            <th class="h-10 w-[110px] border-e border-b border-zinc-200 px-4 text-center align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <button
                                    type="button"
                                    class="group -ms-2 flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                                    @click="toggleSort('status')"
                                >
                                    <span class="w-full text-center">Status</span>
                                    <ChevronsUpDown class="size-3 opacity-60" />
                                </button>
                            </th>
                            <th class="h-10 w-[120px] border-e border-b border-zinc-200 px-4 text-start align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <span>Arrival Date</span>
                            </th>
                            <th class="h-10 w-[140px] border-e border-b border-zinc-200 px-4 text-start align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <span>Supplier</span>
                            </th>
                            <th class="h-10 w-[90px] border-e border-b border-zinc-200 px-4 text-start align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <span>Carrier</span>
                            </th>
                            <th class="h-10 w-[90px] border-e border-b border-zinc-200 px-4 text-center align-middle whitespace-nowrap select-none dark:border-zinc-800">
                                <span>Tracking</span>
                            </th>
                            <th class="h-10 w-[60px] border-b border-zinc-200 px-4 text-center align-middle dark:border-zinc-800" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="11" class="py-12 text-center text-xs text-zinc-400">
                                Loading receipts...
                            </td>
                        </tr>
                        <tr v-else-if="rows.length === 0">
                            <td colspan="11" class="py-12 text-center text-xs text-zinc-400">
                                No inbound shipments found matching the criteria.
                            </td>
                        </tr>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="transition-colors hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40"
                        >
                            <td class="border-e border-b border-zinc-200 px-4 py-3 align-middle dark:border-zinc-800">
                                <input
                                    v-model="selectedRowIds"
                                    type="checkbox"
                                    :value="row.id"
                                    class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 dark:border-zinc-700"
                                />
                            </td>
                            <td class="border-e border-b border-zinc-200 px-4 py-3 align-middle dark:border-zinc-800">
                                <div class="flex min-w-0 flex-col gap-0.5">
                                    <Link
                                        :href="inbound.show.url(row.id)"
                                        class="truncate text-sm font-medium text-zinc-900 transition-colors hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                    >
                                        {{ row.reference_number }}
                                    </Link>
                                    <span class="inline-flex items-center gap-1 text-xs">
                                        <span class="font-mono text-[11px] uppercase text-zinc-400">Source:</span>
                                        <span class="font-mono text-[11px] font-medium text-zinc-700 dark:text-zinc-300">{{ humanize(row.source) }}</span>
                                    </span>
                                </div>
                            </td>
                            <td class="border-e border-b border-zinc-200 px-4 py-3 text-start text-sm font-normal text-zinc-600 align-middle dark:border-zinc-800 dark:text-zinc-400">
                                {{ date(row.created_at || row.received_date) }}
                            </td>
                            <td class="border-e border-b border-zinc-200 px-4 py-3 text-center text-sm font-normal text-zinc-900 align-middle dark:border-zinc-800 dark:text-white">
                                {{ number(row.items_sum_quantity ?? 0) }}
                            </td>
                            <td class="border-e border-b border-zinc-200 px-4 py-3 text-center text-sm font-normal text-zinc-900 align-middle dark:border-zinc-800 dark:text-white">
                                {{ row.items_count }} lines
                            </td>
                            <td class="border-e border-b border-zinc-200 px-4 py-3 text-center align-middle dark:border-zinc-800">
                                <span
                                    class="inline-flex h-6 items-center justify-center rounded-md px-2.5 text-xs font-medium"
                                    :class="getStatusBadgeClass(row.status)"
                                >
                                    {{ humanize(row.status) }}
                                </span>
                            </td>
                            <td class="border-e border-b border-zinc-200 px-4 py-3 text-start text-sm font-normal text-zinc-600 align-middle dark:border-zinc-800 dark:text-zinc-400">
                                {{ row.received_date ? date(row.received_date) : 'Pending' }}
                            </td>
                            <td class="border-e border-b border-zinc-200 px-4 py-3 text-start align-middle dark:border-zinc-800">
                                <div class="flex items-center gap-2">
                                    <span class="size-2 shrink-0 rounded-full bg-rose-500" />
                                    <span class="text-sm font-normal text-zinc-800 dark:text-zinc-200">{{ row.supplier?.company_name ?? 'Internal / N/A' }}</span>
                                </div>
                            </td>
                            <td class="border-e border-b border-zinc-200 px-4 py-3 text-start text-sm font-normal text-zinc-700 align-middle dark:border-zinc-800 dark:text-zinc-300">
                                FedEx
                            </td>
                            <td class="border-e border-b border-zinc-200 px-3 py-3 text-center align-middle dark:border-zinc-800">
                                <button
                                    type="button"
                                    class="inline-flex h-7 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-2.5 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                                    @click="openTrackingDrawer(row)"
                                >
                                    Show
                                </button>
                            </td>
                            <td class="relative border-b border-zinc-200 px-3 py-3 text-center align-middle dark:border-zinc-800">
                                <div class="relative inline-block text-start" @click.stop>
                                    <button
                                        type="button"
                                        class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md p-0 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                        @click="activeRowAction = activeRowAction === row.id ? null : row.id"
                                    >
                                        <EllipsisVertical class="size-4" />
                                    </button>
                                    <div
                                        v-if="activeRowAction === row.id"
                                        class="absolute end-0 top-full z-50 mt-1 w-36 min-w-[8.5rem] overflow-hidden rounded-md border border-zinc-200 bg-white p-1 text-zinc-900 shadow-md shadow-black/10 dark:border-zinc-800 dark:bg-[#18181b] dark:text-zinc-100"
                                    >
                                        <div class="px-2 py-1.5 text-xs font-medium text-zinc-400 select-none dark:text-zinc-500">Actions</div>
                                        <div class="-mx-1 my-1 h-px bg-zinc-200 dark:bg-zinc-800" />
                                        <Link
                                            :href="inbound.show.url(row.id)"
                                            class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs text-zinc-700 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800/80 dark:hover:text-white"
                                        >
                                            <Settings class="size-3.5 opacity-60" />
                                            <span>Settings</span>
                                        </Link>
                                        <button
                                            type="button"
                                            class="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs text-zinc-700 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800/80 dark:hover:text-white"
                                            @click="openTrackingDrawer(row)"
                                        >
                                            <Pencil class="size-3.5 opacity-60" />
                                            <span>Edit</span>
                                        </button>
                                        <div class="-mx-1 my-1 h-px bg-zinc-200 dark:bg-zinc-800" />
                                        <button
                                            type="button"
                                            class="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs text-rose-600 transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
                                            @click="activeRowAction = null"
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
            <div class="flex flex-col items-center justify-between gap-4 border-t border-zinc-200 p-4 text-xs text-zinc-500 sm:flex-row dark:border-zinc-800 dark:text-zinc-400">
                <div class="flex items-center gap-2">
                    <span>Rows per page</span>
                    <select
                        v-model="params.per_page"
                        class="h-7 cursor-pointer rounded-md border border-zinc-200 bg-white px-2 text-xs font-medium text-zinc-700 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>

                <div class="flex items-center gap-4">
                    <span class="font-medium text-zinc-600 dark:text-zinc-400">
                        {{ props.receipts.from ?? 0 }} - {{ props.receipts.to ?? 0 }} of {{ props.receipts.total ?? 0 }}
                    </span>

                    <div class="flex items-center gap-1">
                        <template v-for="(link, idx) in props.receipts.links" :key="idx">
                            <span
                                v-if="!link.url"
                                class="inline-flex h-7 items-center justify-center rounded-md px-2 text-xs text-zinc-400 opacity-50"
                                v-html="link.label"
                            />
                            <Link
                                v-else
                                :href="link.url"
                                class="inline-flex size-7 items-center justify-center rounded-md text-xs font-medium transition-colors"
                                :class="
                                    link.active
                                        ? 'bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-white'
                                        : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800'
                                "
                                preserve-scroll
                            >
                                <span v-html="link.label" />
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TRACK SHIPPING SLIDE-OVER DRAWER (Matches Metronic 720px Drawer)           -->
        <!-- ========================================================================= -->
        <div
            v-if="trackingDrawerOpen"
            class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs transition-opacity duration-300"
            @click="closeTrackingDrawer()"
        />

        <div
            v-if="activeTrackingRow"
            role="dialog"
            aria-modal="true"
            class="fixed top-0 end-0 z-50 flex h-full w-full flex-col overflow-hidden border-s border-zinc-200 bg-white shadow-2xl transition-transform duration-300 ease-in-out dark:border-zinc-800 dark:bg-[#121215] sm:w-[720px]"
            :class="trackingDrawerOpen ? 'translate-x-0' : 'translate-x-full'"
        >
            <!-- Drawer Header -->
            <div class="flex shrink-0 items-center justify-between border-b border-zinc-200 px-6 py-3.5 dark:border-zinc-800">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Track Shipping</h2>
                <button
                    type="button"
                    class="flex size-8 cursor-pointer items-center justify-center rounded-md text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                    @click="closeTrackingDrawer()"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Drawer Body -->
            <div class="flex-1 space-y-6 overflow-y-auto p-6">
                <!-- Top Title & Actions -->
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center gap-2.5">
                            <h3 class="text-xl font-bold text-zinc-900 dark:text-white">{{ activeTrackingRow.reference_number }}</h3>
                            <span
                                class="inline-flex h-5 items-center justify-center rounded px-2 text-xs font-medium"
                                :class="getStatusBadgeClass(activeTrackingRow.status)"
                            >
                                {{ humanize(activeTrackingRow.status) }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                            <span>Placed</span>
                            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ date(activeTrackingRow.created_at || activeTrackingRow.received_date) }}</span>
                            <span class="mx-0.5 size-1 rounded-full bg-zinc-400" />
                            <span>Supplier</span>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ activeTrackingRow.supplier?.company_name ?? 'Internal' }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <button
                            type="button"
                            class="inline-flex h-[34px] cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                            @click="closeTrackingDrawer()"
                        >
                            Close
                        </button>
                        <Link
                            :href="inbound.show.url(activeTrackingRow.id)"
                            class="inline-flex h-[34px] cursor-pointer items-center justify-center rounded-md bg-zinc-950 px-3 text-xs font-medium text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-zinc-950 dark:hover:bg-white"
                        >
                            View Details
                        </Link>
                    </div>
                </div>

                <!-- Stepper Card -->
                <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/40">
                    <div class="flex flex-col items-start justify-between gap-4 border-b border-zinc-200 bg-zinc-50/70 p-4 sm:flex-row sm:items-center dark:border-zinc-800 dark:bg-zinc-800/40">
                        <div class="flex items-start gap-3">
                            <div class="mt-1 flex flex-col items-center gap-1.5">
                                <span class="size-2 shrink-0 rounded-full bg-zinc-900 dark:bg-white" />
                                <div class="h-6 w-0.5 bg-zinc-300 dark:bg-zinc-700" />
                                <span class="size-2 shrink-0 rounded-full bg-zinc-900 dark:bg-white" />
                            </div>
                            <div class="flex flex-col gap-3 text-xs">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">1234 Industrial Way, Dallas, TX 75201</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">Warehouse Dock A, Store Location</span>
                            </div>
                        </div>

                        <div class="inline-flex h-[34px] shrink-0 items-center gap-2 rounded-md border border-zinc-200 bg-white px-3 text-xs font-semibold text-zinc-800 shadow-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                            <Truck class="size-4 text-amber-500" />
                            <span>FedEx Express</span>
                        </div>
                    </div>

                    <!-- 4-Step Progress -->
                    <div class="p-5">
                        <div class="grid grid-cols-4 gap-2 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <div class="h-1.5 w-full rounded-full bg-emerald-500" />
                                <div class="flex items-center gap-1.5">
                                    <CheckCircle2 class="size-4 text-emerald-500 fill-emerald-100 dark:fill-emerald-950" />
                                    <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">Order Placed</span>
                                </div>
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <div class="h-1.5 w-full rounded-full bg-emerald-500" />
                                <div class="flex items-center gap-1.5">
                                    <CheckCircle2 class="size-4 text-emerald-500 fill-emerald-100 dark:fill-emerald-950" />
                                    <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">Dispatched</span>
                                </div>
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <div class="relative h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <div class="h-full w-1/2 rounded-full bg-emerald-500" />
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <CheckCircle2 class="size-4 text-emerald-500" />
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">In Transit</span>
                                </div>
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <div class="h-1.5 w-full rounded-full bg-zinc-200 dark:bg-zinc-700" />
                                <div class="flex items-center gap-1.5">
                                    <Circle class="size-4 text-zinc-400" />
                                    <span class="text-xs font-medium text-zinc-400">Received</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shipping Log Timeline Card -->
                <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/40">
                    <div class="border-b border-zinc-200 bg-zinc-50/70 px-4 py-2.5 text-xs font-semibold text-zinc-900 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-100">
                        Shipment Timeline Log
                    </div>
                    <div class="space-y-5 p-5">
                        <div class="relative flex items-start gap-3">
                            <div class="mt-0.5 flex flex-col items-center gap-1.5">
                                <span class="size-2 shrink-0 rounded-full bg-zinc-900 dark:bg-white" />
                                <div class="h-10 w-0.5 bg-zinc-200 dark:bg-zinc-700" />
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">Receipt Created</span>
                                    <span class="text-[11px] text-zinc-400">{{ date(activeTrackingRow.created_at || activeTrackingRow.received_date) }}</span>
                                </div>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">Inbound receipt created and registered in inventory system</span>
                            </div>
                        </div>
                        <div class="relative flex items-start gap-3">
                            <div class="mt-0.5 flex flex-col items-center gap-1.5">
                                <span class="size-2 shrink-0 rounded-full bg-zinc-900 dark:bg-white" />
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">In Transit</span>
                                    <span class="text-[11px] text-zinc-400">Processing</span>
                                </div>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">Items expected at central fulfillment hub</span>
                                <div class="mt-1 flex items-center gap-1 text-[11px] text-zinc-400">
                                    <MapPin class="size-3 text-zinc-400" />
                                    <span>Main Receiving Dock</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NEW RECEIPT DRAWER / MODAL -->
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
                                <Trash2 />
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
