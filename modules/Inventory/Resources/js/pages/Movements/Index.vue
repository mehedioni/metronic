<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
    Search,
    Settings,
    Trash,
    Truck,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableToolbar from '@/components/TableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useCsvExport } from '@/composables/useCsvExport';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateTime, number } from '@/lib/format';
import { humanize } from '@/lib/status';
import movementRoutes from '@/routes/inventory/movements';
import products from '@/routes/inventory/products';
import type { Paginated } from '@/types';

interface MovementRow {
    id: string;
    type: string;
    quantity: number;
    quantity_before: number;
    quantity_after: number;
    reason: string | null;
    created_at: string;
    product: { id: string; name: string; sku: string | null } | null;
    variant: { id: string; sku: string } | null;
    supplier: { id: string; company_name: string } | null;
    user: { id: number; name: string } | null;
    // Outbound specific fields
    order_id?: string;
    status?: string;
    expected_delivery?: string;
    warehouse?: string;
    carrier?: string;
    tracking_code?: string;
    notify?: boolean;
}

const props = defineProps<{
    movements: Paginated<MovementRow>;
    filters: Record<string, unknown>;
    types: string[];
}>();

const { exportRows } = useCsvExport();

const { params, loading, toggleSort, sortState, reset } = useTableQuery({
    url: movementRoutes.index.url(),
    filters: props.filters,
    only: ['movements', 'filters'],
});

const rows = computed(() => props.movements.data);

const isOutboundView = computed(() => params.direction_flow === 'outbound');

/** Heading dynamically reflects current view */
const heading = computed(() => {
    if (params.direction_flow === 'inbound') {
        return 'Inbound Stock';
    }
    if (params.direction_flow === 'outbound') {
        return 'Outbound Stock';
    }
    return 'Stock Movements';
});

const breadcrumbs = computed(() => [
    { label: 'Store Inventory' },
    { label: 'Inventory' },
    { label: heading.value },
]);

// Ledger view default columns
const ledgerColumns: Column[] = [
    { key: 'created_at', label: 'When', sort: 'created_at', width: '170px' },
    { key: 'product.name', label: 'Product', width: '260px' },
    { key: 'type', label: 'Type', sort: 'type', width: '160px' },
    { key: 'quantity', label: 'Change', sort: 'quantity', align: 'center', width: '100px' },
    { key: 'balance', label: 'Balance', align: 'center', width: '130px' },
    { key: 'reason', label: 'Reason', width: '220px' },
    { key: 'user.name', label: 'By', width: '140px' },
];

function exportCurrent() {
    exportRows('stock-movements', rows.value, [
        { label: 'When', value: (row) => row.created_at },
        { label: 'Product', value: (row) => row.product?.name ?? '' },
        { label: 'SKU', value: (row) => row.variant?.sku ?? row.product?.sku ?? '' },
        { label: 'Type', value: (row) => row.type },
        { label: 'Change', value: (row) => row.quantity },
        { label: 'Before', value: (row) => row.quantity_before },
        { label: 'After', value: (row) => row.quantity_after },
        { label: 'Reason', value: (row) => row.reason ?? '' },
        { label: 'By', value: (row) => row.user?.name ?? '' },
    ]);
}

// Checkbox selection for Outbound Table
const selectedRowIds = ref<string[]>([]);
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

// Dropdowns state
const dateMenuOpen = ref(false);
const statusMenuOpen = ref(false);
const carrierMenuOpen = ref(false);
const statusSearchInput = ref('');
const carrierSearchInput = ref('');
const selectedStatuses = ref<string[]>([]);
const selectedCarriers = ref<string[]>([]);
const activeRowAction = ref<string | null>(null);

// Drawers State
const trackingDrawerOpen = ref(false);
const createLabelDrawerOpen = ref(false);
const activeTrackingRow = ref<MovementRow | null>(null);
const pkgTab = ref<'custom' | 'carrier'>('custom');

function closeAllDropdowns() {
    dateMenuOpen.value = false;
    statusMenuOpen.value = false;
    carrierMenuOpen.value = false;
    activeRowAction.value = null;
}

// Dual Month Range Datepicker Logic (Matches Metronic outbound-stock.html)
const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

const calCurrentDate = ref(new Date(2026, 7, 1));
const rangeStartDate = ref<Date | null>(new Date(2025, 10, 29));
const rangeEndDate = ref<Date | null>(new Date(2026, 7, 24));
const pickingRange = ref(false);

function changeCalendarMonth(delta: number) {
    const d = new Date(calCurrentDate.value);
    d.setMonth(d.getMonth() + delta);
    calCurrentDate.value = d;
}

function selectCalendarDate(year: number, month: number, day: number) {
    const selected = new Date(year, month, day);
    if (!pickingRange.value || !rangeStartDate.value) {
        rangeStartDate.value = selected;
        rangeEndDate.value = null;
        pickingRange.value = true;
    } else {
        if (selected < rangeStartDate.value) {
            rangeEndDate.value = rangeStartDate.value;
            rangeStartDate.value = selected;
        } else {
            rangeEndDate.value = selected;
        }
        pickingRange.value = false;
    }
}

function applyPresetRange(preset: 'today' | 'last30' | 'thisYear') {
    const now = new Date();
    if (preset === 'today') {
        rangeStartDate.value = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        rangeEndDate.value = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    } else if (preset === 'last30') {
        rangeEndDate.value = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        rangeStartDate.value = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 30);
    } else if (preset === 'thisYear') {
        rangeStartDate.value = new Date(now.getFullYear(), 0, 1);
        rangeEndDate.value = new Date(now.getFullYear(), 11, 31);
    }
    calCurrentDate.value = new Date(rangeStartDate.value);
    pickingRange.value = false;
}

const dateBtnLabel = computed(() => {
    if (rangeStartDate.value && rangeEndDate.value) {
        const sMonth = monthNames[rangeStartDate.value.getMonth()].substring(0, 3);
        const eMonth = monthNames[rangeEndDate.value.getMonth()].substring(0, 3);
        return `${sMonth} ${rangeStartDate.value.getDate()} - ${eMonth} ${rangeEndDate.value.getDate()}, ${rangeEndDate.value.getFullYear()}`;
    } else if (rangeStartDate.value) {
        const sMonth = monthNames[rangeStartDate.value.getMonth()].substring(0, 3);
        return `${sMonth} ${rangeStartDate.value.getDate()}, ${rangeStartDate.value.getFullYear()}`;
    }
    return 'Nov 29 - Aug 24, 2026';
});

function getMonthGrid(year: number, month: number) {
    const firstDayIndex = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const prevMonthDays = new Date(year, month, 0).getDate();

    const leadingDays: number[] = [];
    for (let x = firstDayIndex; x > 0; x--) {
        leadingDays.push(prevMonthDays - x + 1);
    }

    const monthDays: number[] = [];
    for (let i = 1; i <= daysInMonth; i++) {
        monthDays.push(i);
    }

    return { year, month, leadingDays, monthDays };
}

const month1Grid = computed(() => {
    const y = calCurrentDate.value.getFullYear();
    const m = calCurrentDate.value.getMonth();
    return getMonthGrid(y, m);
});

const month2Grid = computed(() => {
    const m1Year = calCurrentDate.value.getFullYear();
    const m1Month = calCurrentDate.value.getMonth();
    const m2 = new Date(m1Year, m1Month + 1, 1);
    return getMonthGrid(m2.getFullYear(), m2.getMonth());
});

function getDayCellClass(year: number, month: number, day: number) {
    const currDate = new Date(year, month, day);
    const currTime = currDate.getTime();
    const startTime = rangeStartDate.value
        ? new Date(rangeStartDate.value.getFullYear(), rangeStartDate.value.getMonth(), rangeStartDate.value.getDate()).getTime()
        : null;
    const endTime = rangeEndDate.value
        ? new Date(rangeEndDate.value.getFullYear(), rangeEndDate.value.getMonth(), rangeEndDate.value.getDate()).getTime()
        : null;

    const isStart = startTime && currTime === startTime;
    const isEnd = endTime && currTime === endTime;
    const isMiddle = startTime && endTime && currTime > startTime && currTime < endTime;

    const base = 'size-8 text-xs font-normal flex items-center justify-center cursor-pointer transition-colors relative ';

    if (isStart && isEnd) {
        return base + 'bg-zinc-950 text-white dark:bg-white dark:text-zinc-950 font-medium rounded-md';
    } else if (isStart) {
        return base + 'bg-zinc-950 text-white dark:bg-white dark:text-zinc-950 font-medium rounded-s-md rounded-e-none';
    } else if (isEnd) {
        return base + 'bg-zinc-950 text-white dark:bg-white dark:text-zinc-950 font-medium rounded-e-md rounded-s-none';
    } else if (isMiddle) {
        return base + 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100 rounded-none';
    } else {
        return base + 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-md';
    }
}

// Combobox Labels & Toggles
const statusBtnLabel = computed(() => {
    if (selectedStatuses.value.length === 0) return 'Status';
    return `Status (${selectedStatuses.value.length})`;
});

const carrierBtnLabel = computed(() => {
    if (selectedCarriers.value.length === 0) return 'Carrier';
    return `Carrier (${selectedCarriers.value.length})`;
});

function toggleStatusSelection(status: string) {
    const idx = selectedStatuses.value.indexOf(status);
    if (idx > -1) {
        selectedStatuses.value.splice(idx, 1);
    } else {
        selectedStatuses.value.push(status);
    }
}

function toggleCarrierSelection(carrier: string) {
    const idx = selectedCarriers.value.indexOf(carrier);
    if (idx > -1) {
        selectedCarriers.value.splice(idx, 1);
    } else {
        selectedCarriers.value.push(carrier);
    }
}

function getStatusBadgeClass(status?: string) {
    const s = (status || 'shipped').toLowerCase();
    if (s === 'shipped') return 'text-emerald-800 bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-400';
    if (s === 'processing' || s === 'pending') return 'text-amber-800 bg-amber-100 dark:bg-amber-950 dark:text-amber-400';
    if (s === 'delivered') return 'text-blue-800 bg-blue-100 dark:bg-blue-950 dark:text-blue-400';
    if (s === 'cancelled') return 'text-rose-800 bg-rose-100 dark:bg-rose-950 dark:text-rose-400';
    return 'text-zinc-700 bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-300';
}

function openTrackShipping(row: MovementRow) {
    activeTrackingRow.value = row;
    trackingDrawerOpen.value = true;
}

function toggleRowAction(id: string) {
    if (activeRowAction.value === id) {
        activeRowAction.value = null;
    } else {
        activeRowAction.value = id;
    }
}

const availableCarriers = ['UPS Global', 'FedEx Express', 'DHL Express', 'USPS Ground'];
</script>

<template>
    <Head :title="heading" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Sub-Nav Tabs -->
        <div class="border-b border-dashed border-zinc-200/80 bg-white px-4 pt-4 dark:border-zinc-800/80 dark:bg-[#121215] lg:px-8">
            <div class="flex items-center gap-6 text-xs font-semibold">
                <Link
                    href="/inventory/stock"
                    class="pb-3 text-zinc-500 transition-colors hover:text-zinc-900 dark:hover:text-white"
                >
                    All Stock
                </Link>
                <Link
                    href="/inventory/stock"
                    class="pb-3 text-zinc-500 transition-colors hover:text-zinc-900 dark:hover:text-white"
                >
                    Current Stock
                </Link>
                <Link
                    href="/inventory/inbound"
                    class="pb-3 text-zinc-500 transition-colors hover:text-zinc-900 dark:hover:text-white"
                >
                    Inbound Stock
                </Link>
                <Link
                    href="/inventory/movements?direction_flow=outbound"
                    class="pb-3"
                    :class="
                        isOutboundView
                            ? 'border-b-2 border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400'
                            : 'text-zinc-500 transition-colors hover:text-zinc-900 dark:hover:text-white'
                    "
                >
                    Outbound Stock
                </Link>
            </div>
        </div>

        <main class="flex-1 space-y-6 px-4 py-6 lg:px-8" @click="closeAllDropdowns">
            <!-- OUTBOUND STOCK METRONIC VIEW -->
            <div v-if="isOutboundView" class="rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-[#121215]">
                <!-- Card Toolbar -->
                <div class="flex flex-col items-stretch justify-between gap-3 border-b border-zinc-200 p-4 dark:border-zinc-800 sm:flex-row sm:items-center">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Search Input -->
                        <div class="relative w-full sm:w-48 lg:w-56">
                            <Search class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                            <input
                                v-model="params.search"
                                type="text"
                                placeholder="Search..."
                                class="h-[34px] w-full rounded-md border border-zinc-200 bg-white ps-9 pe-3 text-xs text-zinc-900 placeholder-zinc-400 shadow-xs focus:outline-none focus:ring-1 focus:ring-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                            />
                        </div>

                        <!-- Date Range Calendar Dropdown -->
                        <div class="relative" @click.stop>
                            <button
                                type="button"
                                class="inline-flex h-[34px] cursor-pointer items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                                @click="dateMenuOpen = !dateMenuOpen; statusMenuOpen = false; carrierMenuOpen = false"
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
                                @click="statusMenuOpen = !statusMenuOpen; dateMenuOpen = false; carrierMenuOpen = false"
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
                                        v-for="st in ['shipped', 'processing', 'delivered', 'cancelled']"
                                        :key="st"
                                        class="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-xs select-none transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800/70"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="selectedStatuses.includes(st)"
                                            class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700"
                                            @change="toggleStatusSelection(st)"
                                        />
                                        <span class="flex grow items-center justify-between">
                                            <span
                                                class="inline-flex h-5 items-center justify-center rounded px-2 text-[11px] font-medium"
                                                :class="getStatusBadgeClass(st)"
                                            >
                                                {{ humanize(st) }}
                                            </span>
                                            <span class="me-1 font-semibold text-[11px] text-zinc-400 dark:text-zinc-500">
                                                {{ rows.filter(r => (r.status || 'shipped').toLowerCase() === st).length || 2 }}
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Carrier Filter Dropdown -->
                        <div class="relative" @click.stop>
                            <button
                                type="button"
                                class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                                @click="carrierMenuOpen = !carrierMenuOpen; dateMenuOpen = false; statusMenuOpen = false"
                            >
                                <span>{{ carrierBtnLabel }}</span>
                                <ChevronDown class="ml-0.5 size-4 opacity-60" />
                            </button>
                            <div
                                v-if="carrierMenuOpen"
                                class="absolute start-0 top-full z-50 mt-1.5 w-56 overflow-hidden rounded-lg border border-zinc-200 bg-white text-zinc-900 shadow-xl shadow-black/10 dark:border-zinc-800 dark:bg-[#18181b] dark:text-zinc-100"
                            >
                                <div class="flex items-center border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                                    <Search class="me-2 size-3.5 shrink-0 text-zinc-400" />
                                    <input
                                        v-model="carrierSearchInput"
                                        type="text"
                                        class="w-full bg-transparent text-xs text-zinc-900 placeholder:text-zinc-400 focus:outline-none dark:text-zinc-100"
                                        placeholder="Search carrier..."
                                    />
                                </div>
                                <div class="max-h-60 space-y-0.5 overflow-y-auto p-1.5">
                                    <label
                                        v-for="c in availableCarriers"
                                        :key="c"
                                        class="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-xs select-none transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800/70"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="selectedCarriers.includes(c)"
                                            class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700"
                                            @change="toggleCarrierSelection(c)"
                                        />
                                        <span class="flex grow items-center justify-between">
                                            <span class="text-xs font-normal text-zinc-800 dark:text-zinc-200">{{ c }}</span>
                                            <span class="me-1 font-semibold text-[11px] text-zinc-400 dark:text-zinc-500">
                                                {{ rows.filter(r => (r.carrier || 'UPS Global') === c).length || 3 }}
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Toolbar Action Buttons -->
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex h-8.5 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-[0.8125rem] font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                            @click="createLabelDrawerOpen = true"
                        >
                            <span>Create Shipping Label</span>
                        </button>
                        <Link
                            href="/inventory/stock/planner"
                            class="inline-flex h-8.5 shrink-0 items-center justify-center rounded-md bg-zinc-950 px-3.5 text-[0.8125rem] font-medium text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-zinc-950 dark:hover:bg-white"
                        >
                            <span>Stock Planner</span>
                        </Link>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="min-h-[380px] overflow-x-auto">
                    <table class="w-full min-w-[1130px] border-separate border-spacing-0 caption-bottom text-left text-sm align-middle">
                        <thead>
                            <tr class="bg-zinc-50/50 text-[11px] font-semibold text-zinc-500 dark:bg-zinc-800/40 dark:text-zinc-400">
                                <th class="h-10 w-[35px] border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <input v-model="selectAll" type="checkbox" class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700" />
                                </th>
                                <th class="h-10 w-[120px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <button class="group inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white" @click="toggleSort('created_at')">
                                        <span>Order Date</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="h-10 w-[200px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <button class="group inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white" @click="toggleSort('product.name')">
                                        <span>Product Info</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="h-10 w-[75px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-center align-middle dark:border-zinc-800">
                                    <button class="group inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white" @click="toggleSort('quantity')">
                                        <span class="w-full text-center">QTY</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="h-10 w-[110px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-center align-middle dark:border-zinc-800">
                                    <button class="group inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white">
                                        <span class="w-full text-center">Status</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="h-10 w-[125px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <button class="group inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white">
                                        <span>Exp. Delivery</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="h-10 w-[120px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <button class="group inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white">
                                        <span>Warehouse</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="h-10 w-[90px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <button class="group inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white">
                                        <span>Carrier</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="h-10 w-[90px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-center align-middle dark:border-zinc-800">
                                    <span>Tracking</span>
                                </th>
                                <th class="h-10 w-[60px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-center align-middle dark:border-zinc-800">
                                    <span>Notify</span>
                                </th>
                                <th class="h-10 w-[70px] border-b border-zinc-200 px-4 text-center align-middle dark:border-zinc-800"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in rows"
                                :key="row.id"
                                class="transition-colors hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40"
                            >
                                <td class="border-b border-e border-zinc-200 px-4 py-3 align-middle dark:border-zinc-800">
                                    <input
                                        v-model="selectedRowIds"
                                        type="checkbox"
                                        :value="row.id"
                                        class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700"
                                    />
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3 text-start align-middle text-sm font-normal text-zinc-900 dark:border-zinc-800 dark:text-zinc-100">
                                    {{ row.order_id || 'SO-AMS-4620' }}
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3 align-middle dark:border-zinc-800">
                                    <div class="flex min-w-0 flex-col gap-0.5">
                                        <Link
                                            v-if="row.product"
                                            :href="products.show.url(row.product.id)"
                                            class="truncate text-sm font-medium text-zinc-900 transition-colors hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                        >
                                            {{ row.product.name }}
                                        </Link>
                                        <span v-else class="truncate text-sm font-medium text-zinc-900 dark:text-white">Nike Air Max 270 React SE</span>
                                        <span class="inline-flex items-center gap-1 text-xs">
                                            <span class="font-mono text-[11px] text-zinc-400 uppercase">SKU:</span>
                                            <span class="font-mono text-[11px] font-medium text-zinc-700 dark:text-zinc-300">{{ row.variant?.sku || row.product?.sku || 'WM-8421' }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3 text-center align-middle text-sm font-normal text-zinc-900 dark:border-zinc-800 dark:text-white">
                                    {{ Math.abs(row.quantity) }}
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3 text-center align-middle dark:border-zinc-800">
                                    <span
                                        class="inline-flex h-6 items-center justify-center rounded-md px-2.5 text-xs font-medium"
                                        :class="getStatusBadgeClass(row.status)"
                                    >
                                        {{ humanize(row.status || 'Shipped') }}
                                    </span>
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3 text-start align-middle text-sm font-normal text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">
                                    {{ row.expected_delivery || '17 Apr, 2026' }}
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3 text-start align-middle text-sm font-normal text-zinc-700 dark:border-zinc-800 dark:text-zinc-300">
                                    {{ row.warehouse || 'Main Hub' }}
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3 text-start align-middle text-sm font-normal text-zinc-700 dark:border-zinc-800 dark:text-zinc-300">
                                    {{ row.carrier || 'UPS Global' }}
                                </td>
                                <td class="border-b border-e border-zinc-200 px-3 py-3 text-center align-middle dark:border-zinc-800">
                                    <button
                                        type="button"
                                        class="inline-flex h-7 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-2.5 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                                        @click="openTrackShipping(row)"
                                    >
                                        Show
                                    </button>
                                </td>
                                <td class="border-b border-e border-zinc-200 px-4 py-3 text-center align-middle dark:border-zinc-800">
                                    <div class="flex justify-center">
                                        <input
                                            type="checkbox"
                                            :checked="row.notify !== false"
                                            class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700"
                                        />
                                    </div>
                                </td>
                                <td class="relative border-b border-zinc-200 px-3 py-3 text-center align-middle dark:border-zinc-800" @click.stop>
                                    <button
                                        type="button"
                                        class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md p-0 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                        @click="toggleRowAction(row.id)"
                                    >
                                        <EllipsisVertical class="size-4" />
                                    </button>
                                    <!-- Row Action Dropdown Popover (1:1 with reference) -->
                                    <div
                                        v-if="activeRowAction === row.id"
                                        class="absolute right-0 top-full z-50 mt-1 min-w-[8.5rem] w-36 overflow-hidden rounded-md border border-zinc-200 bg-white p-1 text-zinc-900 shadow-md shadow-black/10 dark:border-zinc-800 dark:bg-[#18181b] dark:text-zinc-100"
                                    >
                                        <div class="select-none px-2 py-1.5 text-xs font-medium text-zinc-400 dark:text-zinc-500">Actions</div>
                                        <div class="-mx-1 my-1 h-px bg-zinc-200 dark:bg-zinc-800" />
                                        <button
                                            type="button"
                                            class="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-start text-xs text-zinc-700 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800/80 dark:hover:text-white"
                                            @click="closeAllDropdowns"
                                        >
                                            <Settings class="size-3.5 opacity-60" />
                                            <span>Settings</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-start text-xs text-zinc-700 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800/80 dark:hover:text-white"
                                            @click="closeAllDropdowns"
                                        >
                                            <Pencil class="size-3.5 opacity-60" />
                                            <span>Edit</span>
                                        </button>
                                        <div class="-mx-1 my-1 h-px bg-zinc-200 dark:bg-zinc-800" />
                                        <button
                                            type="button"
                                            class="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-start text-xs text-rose-600 transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
                                            @click="closeAllDropdowns"
                                        >
                                            <Trash class="size-3.5 opacity-60" />
                                            <span>Delete</span>
                                        </button>
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
                        <div class="relative">
                            <select
                                v-model="params.per_page"
                                class="inline-flex h-7 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-2 text-xs font-medium text-zinc-700 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                            >
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <span class="font-medium text-zinc-600 dark:text-zinc-400">
                            {{ props.movements.from || 1 }} - {{ props.movements.to || rows.length }} of {{ props.movements.total || rows.length }}
                        </span>
                        <Pagination
                            :links="props.movements.links"
                            :from="props.movements.from"
                            :to="props.movements.to"
                            :total="props.movements.total"
                        />
                    </div>
                </div>
            </div>
        </main>

        <!-- TRACK SHIPPING DRAWER / SHEET (1:1 with Metronic outbound-stock.html) -->
        <div
            v-if="trackingDrawerOpen"
            class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs transition-opacity"
            @click="trackingDrawerOpen = false"
        />

        <div
            class="fixed top-0 bottom-0 end-0 z-50 flex w-full flex-col justify-between overflow-hidden border-s border-zinc-200 bg-white shadow-2xl transition-transform duration-300 dark:border-zinc-800 dark:bg-[#121215] sm:w-[720px]"
            :class="trackingDrawerOpen ? 'translate-x-0' : 'translate-x-full'"
        >
            <!-- Drawer Header -->
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-3.5 dark:border-zinc-800">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Track Shipping</h2>
                <button
                    type="button"
                    class="flex size-8 items-center justify-center rounded-md text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                    @click="trackingDrawerOpen = false"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Drawer Body (Scrollable) -->
            <div class="flex-1 space-y-6 overflow-y-auto p-6">
                <!-- Top Title & Action Buttons -->
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center gap-2.5">
                            <h3 class="text-xl font-bold text-zinc-900 dark:text-white">{{ activeTrackingRow?.tracking_code || 'SHP-3827462' }}</h3>
                            <span
                                class="inline-flex h-5 items-center justify-center rounded px-2 text-xs font-medium"
                                :class="getStatusBadgeClass(activeTrackingRow?.status)"
                            >
                                {{ humanize(activeTrackingRow?.status || 'Shipped') }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                            <span>Placed</span>
                            <span class="font-medium text-zinc-800 dark:text-zinc-200">2022-01-01</span>
                            <span class="mx-0.5 size-1 rounded-full bg-zinc-400" />
                            <span>Order ID</span>
                            <a class="font-medium text-zinc-900 underline dark:text-white" href="javascript:void(0)">
                                {{ activeTrackingRow?.order_id || 'SO-AMS-4620' }}
                            </a>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <button
                            type="button"
                            class="inline-flex h-[34px] cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                        >
                            Cancel Order
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-[34px] cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                        >
                            Notify Customer
                        </button>
                    </div>
                </div>

                <!-- Card 1: Route & Stepper -->
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
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">8458 Sunset Blvd #209, Los Angeles, CA 90069</span>
                            </div>
                        </div>

                        <div class="inline-flex h-[34px] shrink-0 items-center gap-2 rounded-md border border-zinc-200 bg-white px-3 text-xs font-semibold text-zinc-800 shadow-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                            <Truck class="size-4 text-amber-500" />
                            <span>{{ activeTrackingRow?.carrier || 'UPS Global' }}</span>
                        </div>
                    </div>

                    <!-- 4-Step Stepper -->
                    <div class="p-5">
                        <div class="grid grid-cols-4 gap-2 text-center">
                            <!-- Step 1: Picking -->
                            <div class="flex flex-col items-center gap-2">
                                <div class="h-1.5 w-full rounded-full bg-emerald-500" />
                                <div class="flex items-center gap-1.5">
                                    <CheckCircle2 class="size-4 text-emerald-500 fill-emerald-100 dark:fill-emerald-950" />
                                    <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">Picking</span>
                                </div>
                            </div>

                            <!-- Step 2: Packed -->
                            <div class="flex flex-col items-center gap-2">
                                <div class="h-1.5 w-full rounded-full bg-emerald-500" />
                                <div class="flex items-center gap-1.5">
                                    <CheckCircle2 class="size-4 text-emerald-500 fill-emerald-100 dark:fill-emerald-950" />
                                    <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">Packed</span>
                                </div>
                            </div>

                            <!-- Step 3: Shipping -->
                            <div class="flex flex-col items-center gap-2">
                                <div class="relative h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <div class="h-full w-1/2 rounded-full bg-emerald-500" />
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <CheckCircle2 class="size-4 text-emerald-500" />
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">Shipping</span>
                                </div>
                            </div>

                            <!-- Step 4: Delivered -->
                            <div class="flex flex-col items-center gap-2">
                                <div class="h-1.5 w-full rounded-full bg-zinc-200 dark:bg-zinc-700" />
                                <div class="flex items-center gap-1.5">
                                    <Circle class="size-4 text-zinc-400" />
                                    <span class="text-xs font-medium text-zinc-400">Delivered</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Shipping Data -->
                <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/40">
                    <div class="border-b border-zinc-200 bg-zinc-50/70 px-4 py-2.5 text-xs font-semibold text-zinc-900 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-100">
                        Shipping Data
                    </div>
                    <div class="grid grid-cols-2 gap-4 p-4 sm:grid-cols-4">
                        <div class="flex flex-col gap-1">
                            <span class="text-xs text-zinc-400">Total Time</span>
                            <span class="text-xs font-semibold text-zinc-900 dark:text-white">19 days, 7 hours</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-xs text-zinc-400">Dep. Time</span>
                            <span class="text-xs font-semibold text-zinc-900 dark:text-white">01 Aug, 2025 09:17</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-xs text-zinc-400">Exp. Arrival</span>
                            <span class="text-xs font-semibold text-zinc-900 dark:text-white">17 Apr, 2025 12:00</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-xs text-zinc-400">Tracking No.</span>
                            <span class="text-xs font-semibold text-zinc-900 dark:text-white">1Z999AA10123456784</span>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Shipping Log Timeline -->
                <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/40">
                    <div class="border-b border-zinc-200 bg-zinc-50/70 px-4 py-2.5 text-xs font-semibold text-zinc-900 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-100">
                        Shipping Log
                    </div>
                    <div class="space-y-5 p-5">
                        <!-- Log 1 -->
                        <div class="relative flex items-start gap-3">
                            <div class="mt-0.5 flex flex-col items-center gap-1.5">
                                <span class="size-2 shrink-0 rounded-full bg-zinc-900 dark:bg-white" />
                                <div class="h-12 w-0.5 bg-zinc-200 dark:bg-zinc-700" />
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">Order Placed</span>
                                    <span class="text-[11px] text-zinc-400">28 Jul, 2025 10:02</span>
                                </div>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">Shipment information received by seller</span>
                                <div class="mt-1 flex items-center gap-1 text-[11px] text-zinc-400">
                                    <MapPin class="size-3 text-zinc-400" />
                                    <span>Silicon Valley, CA</span>
                                </div>
                            </div>
                        </div>

                        <!-- Log 2 -->
                        <div class="relative flex items-start gap-3">
                            <div class="mt-0.5 flex flex-col items-center gap-1.5">
                                <span class="size-2 shrink-0 rounded-full bg-zinc-900 dark:bg-white" />
                                <div class="h-8 w-0.5 bg-zinc-200 dark:bg-zinc-700" />
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">Picking</span>
                                    <span class="text-[11px] text-zinc-400">28 Jul, 2025 11:02</span>
                                </div>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">Items being picked from inventory</span>
                            </div>
                        </div>

                        <!-- Log 3 -->
                        <div class="relative flex items-start gap-3">
                            <div class="mt-0.5 flex flex-col items-center gap-1.5">
                                <span class="size-2 shrink-0 rounded-full bg-zinc-900 dark:bg-white" />
                                <div class="h-8 w-0.5 bg-zinc-200 dark:bg-zinc-700" />
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">Packed</span>
                                    <span class="text-[11px] text-zinc-400">28 Jul, 2025 12:27</span>
                                </div>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">Shipment information received by seller</span>
                            </div>
                        </div>

                        <!-- Log 4 -->
                        <div class="relative flex items-start gap-3">
                            <div class="mt-0.5 flex flex-col items-center gap-1.5">
                                <span class="size-2 shrink-0 rounded-full bg-zinc-900 dark:bg-white" />
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">Shipped</span>
                                    <span class="text-[11px] text-zinc-400">28 Jul, 2025 14:27</span>
                                </div>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">Package handed off to carrier</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-zinc-200 p-4 dark:border-zinc-800">
                <button
                    type="button"
                    class="inline-flex h-[34px] w-full cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-4 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                    @click="trackingDrawerOpen = false"
                >
                    Close
                </button>
            </div>
        </div>

        <!-- CREATE SHIPPING LABEL DRAWER / SHEET (940px 2-column layout 1:1 matching reference) -->
        <div
            v-if="createLabelDrawerOpen"
            class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs transition-opacity"
            @click="createLabelDrawerOpen = false"
        />

        <div
            class="fixed top-0 bottom-0 end-0 z-50 flex w-full flex-col justify-between overflow-hidden border-s border-zinc-200 bg-white shadow-2xl transition-transform duration-300 dark:border-zinc-800 dark:bg-[#121215] sm:w-[940px]"
            :class="createLabelDrawerOpen ? 'translate-x-0' : 'translate-x-full'"
        >
            <!-- Drawer Header -->
            <div class="flex shrink-0 items-center justify-between border-b border-zinc-200 px-6 py-3.5 dark:border-zinc-800">
                <h2 class="flex items-center gap-2.5 text-base font-semibold text-zinc-900 dark:text-zinc-100">Create Shipping Label</h2>
                <button
                    type="button"
                    class="flex size-8 items-center justify-center rounded-md text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                    @click="createLabelDrawerOpen = false"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Drawer Body -->
            <div class="flex-1 overflow-y-auto p-6">
                <div class="flex flex-col gap-6 lg:flex-row">
                    <!-- Left Column -->
                    <div class="flex-1 space-y-5 lg:border-e lg:border-zinc-200 lg:pe-6 dark:lg:border-zinc-800">
                        <!-- Route Card -->
                        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/40">
                            <div class="flex items-start gap-3 border-b border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-800/40">
                                <div class="mt-1 flex flex-col items-center gap-1">
                                    <span class="size-2 shrink-0 rounded-full bg-zinc-900 dark:bg-white" />
                                    <div class="h-6 w-0.5 bg-zinc-300 dark:bg-zinc-700" />
                                    <span class="size-2 shrink-0 rounded-full bg-zinc-900 dark:bg-white" />
                                </div>
                                <div class="flex flex-col gap-3 text-xs">
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">1234 Industrial Way, Dallas, TX 75201</span>
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">8458 Sunset Blvd #209, Los Angeles, CA 90069</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 p-4 sm:grid-cols-4">
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs text-zinc-400">Order ID</span>
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">SO-AMS-4620</span>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs text-zinc-400">Placed</span>
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">28 Jul, 2025</span>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs text-zinc-400">Total Price</span>
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">$320.00</span>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs text-zinc-400">Shipping Priority</span>
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">High</span>
                                </div>
                            </div>
                        </div>

                        <!-- Items Card -->
                        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/40">
                            <div class="border-b border-zinc-200 bg-zinc-50/70 px-4 py-2.5 text-xs font-semibold text-zinc-900 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-100">
                                Team
                            </div>
                            <div class="space-y-4 p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-12 shrink-0 items-center justify-center rounded-lg border border-zinc-100 bg-zinc-50 p-1 dark:border-zinc-800 dark:bg-zinc-800/60">
                                            <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300">AIR</span>
                                        </div>
                                        <div class="flex flex-col gap-0.5">
                                            <span class="text-xs font-semibold text-zinc-900 dark:text-white">Nike Air Max 270 React SE</span>
                                            <div class="flex items-center gap-2 text-xs text-zinc-400">
                                                <span>SKU: <span class="font-medium text-zinc-700 dark:text-zinc-300">WM-8421</span></span>
                                                <span class="size-1 rounded-full bg-zinc-300 dark:bg-zinc-700" />
                                                <span>Color <span class="font-medium text-zinc-700 dark:text-zinc-300">Beige</span></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end gap-1">
                                        <span class="text-[11px] font-medium text-zinc-500">Weight</span>
                                        <div class="flex h-7 w-[72px] items-center rounded-md border border-zinc-200 bg-white px-2 py-0.5 text-xs dark:border-zinc-700 dark:bg-zinc-800">
                                            <input type="text" value="1.2" class="w-full bg-transparent text-end text-xs font-semibold text-zinc-900 pe-1 focus:outline-none dark:text-white" />
                                            <span class="text-[11px] text-zinc-400">kg</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Packaging Card -->
                        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/40">
                            <div class="flex items-center justify-between border-b border-zinc-200 bg-zinc-50/70 px-4 dark:border-zinc-800 dark:bg-zinc-800/40">
                                <h3 class="py-3 text-xs font-semibold text-zinc-900 dark:text-zinc-100">Packaging</h3>
                                <div class="flex items-center gap-4">
                                    <button
                                        type="button"
                                        class="py-3 text-xs"
                                        :class="pkgTab === 'custom' ? 'border-b-2 border-zinc-950 font-semibold text-zinc-950 dark:border-white dark:text-white' : 'font-medium text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200'"
                                        @click="pkgTab = 'custom'"
                                    >
                                        Custom Package
                                    </button>
                                    <button
                                        type="button"
                                        class="py-3 text-xs"
                                        :class="pkgTab === 'carrier' ? 'border-b-2 border-zinc-950 font-semibold text-zinc-950 dark:border-white dark:text-white' : 'font-medium text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200'"
                                        @click="pkgTab = 'carrier'"
                                    >
                                        Carrier Package
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-4 p-4">
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Package Name</label>
                                    <input type="text" value="Mike Anderson – Medium Box" class="h-[34px] rounded-md border border-zinc-200 bg-white px-3 text-xs text-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Package Type</label>
                                        <select class="h-[34px] cursor-pointer rounded-md border border-zinc-200 bg-white px-3 text-xs text-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                            <option selected>Medium Box</option>
                                            <option>Small Box</option>
                                            <option>Large Box</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Total Weight</label>
                                        <div class="flex h-[34px] items-center rounded-md border border-zinc-200 bg-white px-3 dark:border-zinc-700 dark:bg-zinc-800">
                                            <input type="text" value="2.1" class="w-full bg-transparent text-xs text-zinc-900 focus:outline-none dark:text-zinc-100" />
                                            <span class="text-xs text-zinc-400">kg</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-4 gap-3">
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Length</label>
                                        <input type="text" value="48" class="h-[34px] rounded-md border border-zinc-200 bg-white px-3 text-xs text-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Width</label>
                                        <input type="text" value="36" class="h-[34px] rounded-md border border-zinc-200 bg-white px-3 text-xs text-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Height</label>
                                        <input type="text" value="20" class="h-[34px] rounded-md border border-zinc-200 bg-white px-3 text-xs text-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Unit</label>
                                        <select class="h-[34px] cursor-pointer rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                            <option selected>cm</option>
                                            <option>in</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 pt-1">
                                    <input id="save-future-pkg" type="checkbox" checked class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700" />
                                    <label for="save-future-pkg" class="cursor-pointer text-xs font-medium text-zinc-700 select-none dark:text-zinc-300">Save package for future orders</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column Summary Sidebar -->
                    <div class="w-full shrink-0 space-y-5 lg:w-[320px]">
                        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/40">
                            <div class="border-b border-zinc-200 bg-zinc-50/70 px-4 py-2.5 text-xs font-semibold text-zinc-900 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-100">
                                Summary
                            </div>
                            <div class="space-y-3.5 p-4">
                                <div class="flex flex-col gap-0.5">
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">Shipping to Jeroen’s Home</span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">Prinsengracht 24</span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">1015 DV Amsterdam, NL</span>
                                </div>
                                <div class="h-px bg-zinc-100 dark:bg-zinc-800" />
                                <div class="space-y-2">
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-white">Price Details</span>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-zinc-500 dark:text-zinc-400">Subtotal</span>
                                        <span class="font-medium text-zinc-900 dark:text-white">$19.00</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-zinc-500 dark:text-zinc-400">Discount</span>
                                        <span class="font-medium text-zinc-900 dark:text-white">$00.00</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-zinc-500 dark:text-zinc-400">Total</span>
                                        <span class="font-medium text-zinc-900 dark:text-white">$3.99</span>
                                    </div>
                                </div>
                                <div class="h-px bg-zinc-100 dark:bg-zinc-800" />
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-semibold text-zinc-900 dark:text-white">Total</span>
                                    <span class="text-sm font-bold text-zinc-900 dark:text-white">$22.99</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Shipping Date</label>
                            <div class="flex h-[34px] items-center justify-between rounded-md border border-zinc-200 bg-white px-3 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                <span>Active</span>
                                <CalendarIcon class="size-4 text-zinc-400" />
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <input id="send-customer-info" type="checkbox" class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700" />
                            <label for="send-customer-info" class="cursor-pointer text-xs font-medium text-zinc-600 select-none dark:text-zinc-400">
                                Send <a href="javascript:void(0)" class="text-blue-600 hover:underline dark:text-blue-400">Shipping Info</a> to Customer
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Drawer Footer -->
            <div class="flex shrink-0 flex-col items-center justify-between gap-3 border-t border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-[#121215] sm:flex-row">
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    Read Shipping <a href="javascript:void(0)" class="font-medium text-blue-600 hover:underline dark:text-blue-400">Terms & Conditions</a>
                </div>
                <div class="flex w-full items-center gap-2.5 sm:w-auto">
                    <button
                        type="button"
                        class="inline-flex h-[34px] w-full cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-4 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60 sm:w-auto"
                        @click="createLabelDrawerOpen = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-[34px] w-full cursor-pointer items-center justify-center rounded-md bg-zinc-950 px-4 text-xs font-semibold text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-zinc-950 dark:hover:bg-white sm:w-auto"
                        @click="createLabelDrawerOpen = false"
                    >
                        Buy Shipping Label
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
