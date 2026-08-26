<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Calendar as CalendarIcon,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    EllipsisVertical,
    Pencil,
    Search,
    Settings,
    Trash,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Pagination from '@/components/Pagination.vue';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, dateTime } from '@/lib/format';
import { humanize } from '@/lib/status';
import movementRoutes from '@/routes/inventory/movements';
import products from '@/routes/inventory/products';
import type { Paginated } from '@/types';

interface MovementRow {
    id: number;
    type: string;
    quantity: number;
    quantity_before: number;
    quantity_after: number;
    reason: string | null;
    created_at: string;
    product: { id: number; name: string; sku: string | null } | null;
    variant: { id: number; sku: string } | null;
    supplier: { id: number; company_name: string } | null;
    user: { id: number; name: string } | null;
    // Outbound specific fields
    notify?: boolean;
}

const props = defineProps<{
    movements: Paginated<MovementRow>;
    filters: Record<string, unknown>;
    types: string[];
}>();


const { params, toggleSort } = useTableQuery({
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
const statusSearchInput = ref('');
const selectedStatuses = ref<string[]>([]);
const activeRowAction = ref<string | null>(null);

// Drawers State

function closeAllDropdowns() {
    dateMenuOpen.value = false;
    statusMenuOpen.value = false;
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


function toggleStatusSelection(status: string) {
    const idx = selectedStatuses.value.indexOf(status);
    if (idx > -1) {
        selectedStatuses.value.splice(idx, 1);
    } else {
        selectedStatuses.value.push(status);
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

function toggleRowAction(id: string) {
    if (activeRowAction.value === id) {
        activeRowAction.value = null;
    } else {
        activeRowAction.value = id;
    }
}

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
                                @click="dateMenuOpen = !dateMenuOpen; statusMenuOpen = false; "
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
                                            <span class="flex size-8 items-center justify-center text-2xs font-medium text-zinc-400">Su</span>
                                            <span class="flex size-8 items-center justify-center text-2xs font-medium text-zinc-400">Mo</span>
                                            <span class="flex size-8 items-center justify-center text-2xs font-medium text-zinc-400">Tu</span>
                                            <span class="flex size-8 items-center justify-center text-2xs font-medium text-zinc-400">We</span>
                                            <span class="flex size-8 items-center justify-center text-2xs font-medium text-zinc-400">Th</span>
                                            <span class="flex size-8 items-center justify-center text-2xs font-medium text-zinc-400">Fr</span>
                                            <span class="flex size-8 items-center justify-center text-2xs font-medium text-zinc-400">Sa</span>
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
                                            <span class="flex size-8 items-center justify-center text-2xs font-medium text-zinc-400">Su</span>
                                            <span class="flex size-8 items-center justify-center text-2xs font-medium text-zinc-400">Mo</span>
                                            <span class="flex size-8 items-center justify-center text-2xs font-medium text-zinc-400">Tu</span>
                                            <span class="flex size-8 items-center justify-center text-2xs font-medium text-zinc-400">We</span>
                                            <span class="flex size-8 items-center justify-center text-2xs font-medium text-zinc-400">Th</span>
                                            <span class="flex size-8 items-center justify-center text-2xs font-medium text-zinc-400">Fr</span>
                                            <span class="flex size-8 items-center justify-center text-2xs font-medium text-zinc-400">Sa</span>
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
                                            class="rounded-md bg-zinc-100 px-2.5 py-1 text-2xs font-medium text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                                            @click="applyPresetRange('today')"
                                        >
                                            Today
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md bg-zinc-100 px-2.5 py-1 text-2xs font-medium text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                                            @click="applyPresetRange('last30')"
                                        >
                                            Last 30 Days
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md bg-zinc-100 px-2.5 py-1 text-2xs font-medium text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
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
                                class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-2sm font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700/60"
                                @click="statusMenuOpen = !statusMenuOpen; dateMenuOpen = false; "
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
                                                class="inline-flex h-5 items-center justify-center rounded px-2 text-2xs font-medium"
                                                :class="getStatusBadgeClass(st)"
                                            >
                                                {{ humanize(st) }}
                                            </span>
                                            <span class="me-1 font-semibold text-2xs text-zinc-400 dark:text-zinc-500">
                                                {{ rows.filter(r => (r.status || 'shipped').toLowerCase() === st).length || 2 }}
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Toolbar Action Buttons -->
                    <div class="flex items-center gap-2">
                        <Link
                            href="/inventory/stock/planner"
                            class="inline-flex h-8.5 shrink-0 items-center justify-center rounded-md bg-zinc-950 px-3.5 text-2sm font-medium text-white shadow-xs transition-colors hover:bg-zinc-900 dark:bg-zinc-200 dark:text-zinc-950 dark:hover:bg-white"
                        >
                            <span>Stock Planner</span>
                        </Link>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="min-h-[380px] overflow-x-auto">
                    <table class="w-full min-w-[1130px] border-separate border-spacing-0 caption-bottom text-left text-sm align-middle">
                        <thead>
                            <tr class="bg-zinc-50/50 text-2xs font-semibold text-zinc-500 dark:bg-zinc-800/40 dark:text-zinc-400">
                                <th class="h-10 w-[35px] border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <input v-model="selectAll" type="checkbox" class="size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700" />
                                </th>
                                <th class="h-10 w-[120px] select-none whitespace-nowrap border-b border-e border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    <button class="group inline-flex h-7 w-full cursor-pointer items-center justify-between gap-1.5 rounded-md px-2 text-xs font-normal text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white" @click="toggleSort('created_at')">
                                        <span>Date</span>
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
                                        <span class="w-full text-center">Type</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
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
                                    {{ date(row.created_at) }}
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
                                            <span class="font-mono text-2xs text-zinc-400 uppercase">SKU:</span>
                                            <span class="font-mono text-2xs font-medium text-zinc-700 dark:text-zinc-300">{{ row.variant?.sku || row.product?.sku || 'WM-8421' }}</span>
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
                                        {{ humanize(row.type) }}
                                    </span>
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

            <!-- STOCK MOVEMENTS VIEW (When viewing /inventory/movements) -->
            <div v-else class="rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-[#121215]">
                <!-- Card Toolbar -->
                <div class="flex flex-col items-stretch justify-between gap-3 border-b border-zinc-200 p-4 dark:border-zinc-800 sm:flex-row sm:items-center">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Search Input -->
                        <div class="relative w-full sm:w-48 lg:w-56">
                            <Search class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                            <input
                                v-model="params.search"
                                type="text"
                                placeholder="Search movements..."
                                class="h-[34px] w-full rounded-md border border-zinc-200 bg-white ps-9 pe-3 text-xs text-zinc-900 placeholder-zinc-400 shadow-xs focus:outline-none focus:ring-1 focus:ring-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                            />
                        </div>

                        <!-- Movement Type Filter -->
                        <div class="relative">
                            <select
                                v-model="params.type"
                                class="inline-flex h-[34px] cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-xs font-medium text-zinc-700 shadow-xs focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                            >
                                <option value="">All Types</option>
                                <option v-for="t in props.types" :key="t" :value="t">
                                    {{ humanize(t) }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="min-h-[380px] overflow-x-auto">
                    <table class="w-full min-w-[960px] table-fixed border-separate border-spacing-0 text-left align-middle text-sm">
                        <thead>
                            <tr class="bg-zinc-50/50 text-2xs font-semibold text-zinc-500 dark:bg-zinc-800/40 dark:text-zinc-400">
                                <th class="h-10 w-[240px] border-e border-b border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    Product
                                </th>
                                <th class="h-10 w-[140px] border-e border-b border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    Type
                                </th>
                                <th class="h-10 w-[100px] border-e border-b border-zinc-200 px-4 text-center align-middle dark:border-zinc-800">
                                    Change
                                </th>
                                <th class="h-10 w-[130px] border-e border-b border-zinc-200 px-4 text-center align-middle dark:border-zinc-800">
                                    Before / After
                                </th>
                                <th class="h-10 w-[150px] border-e border-b border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    Actor / Supplier
                                </th>
                                <th class="h-10 w-[140px] border-e border-b border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    Date & Time
                                </th>
                                <th class="h-10 w-[140px] border-b border-zinc-200 px-4 text-start align-middle dark:border-zinc-800">
                                    Reason
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            <tr v-if="rows.length === 0">
                                <td colspan="7" class="border-b border-zinc-200 py-12 text-center text-sm text-zinc-400 dark:border-zinc-800">
                                    No stock movements recorded yet.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.id"
                                class="transition-colors hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40"
                            >
                                <!-- Product -->
                                <td class="border-e border-b border-zinc-200 px-4 py-3 align-middle dark:border-zinc-800">
                                    <div class="flex min-w-0 flex-col gap-0.5">
                                        <span class="truncate text-sm font-medium text-zinc-900 dark:text-white">
                                            {{ row.product?.name ?? '—' }}
                                        </span>
                                        <span class="font-mono text-2xs text-zinc-400">
                                            {{ row.variant?.sku ?? row.product?.sku ?? '—' }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Type Badge -->
                                <td class="border-e border-b border-zinc-200 px-4 py-3 align-middle dark:border-zinc-800">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold"
                                        :class="
                                            row.quantity > 0
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400'
                                                : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400'
                                        "
                                    >
                                        {{ humanize(row.type) }}
                                    </span>
                                </td>

                                <!-- Change -->
                                <td class="border-e border-b border-zinc-200 px-4 py-3 text-center align-middle font-mono font-semibold dark:border-zinc-800">
                                    <span :class="row.quantity > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                        {{ row.quantity > 0 ? `+${row.quantity}` : row.quantity }}
                                    </span>
                                </td>

                                <!-- Before / After -->
                                <td class="border-e border-b border-zinc-200 px-4 py-3 text-center align-middle text-xs text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">
                                    <span class="font-mono">{{ row.quantity_before }}</span>
                                    <span class="mx-1 text-zinc-400">→</span>
                                    <span class="font-mono font-medium text-zinc-900 dark:text-white">{{ row.quantity_after }}</span>
                                </td>

                                <!-- Actor / Supplier -->
                                <td class="border-e border-b border-zinc-200 px-4 py-3 text-start align-middle text-xs text-zinc-700 dark:border-zinc-800 dark:text-zinc-300">
                                    {{ row.user?.name ?? row.supplier?.company_name ?? 'System' }}
                                </td>

                                <!-- Date & Time -->
                                <td class="border-e border-b border-zinc-200 px-4 py-3 text-start align-middle text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                                    {{ dateTime(row.created_at) }}
                                </td>

                                <!-- Reason -->
                                <td class="border-b border-zinc-200 px-4 py-3 text-start align-middle text-xs text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">
                                    {{ row.reason ?? '—' }}
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
                                <option :value="20">20</option>
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


        <!-- CREATE SHIPPING LABEL DRAWER / SHEET (940px 2-column layout 1:1 matching reference) -->

    </AppLayout>
</template>
