<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronsUpDown,
    Circle,
    Eye,
    FileSpreadsheet,
    Image as ImageIcon,
    MoreVertical,
    Package,
    Pencil,
    Plus,
    Printer,
    SquareMinus,
    SquarePlus,
    Trash2,
    Truck,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Pagination from '@/components/Pagination.vue';
import DateRangePicker from '@/components/ui/DateRangePicker.vue';
import { Drawer } from '@/components/ui/drawer';
import { Dropdown } from '@/components/ui/dropdown';
import { useCsvExport } from '@/composables/useCsvExport';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, money, number } from '@/lib/format';
import orderRoutes from '@/routes/inventory/orders';
import stockRoutes from '@/routes/inventory/stock';
import type { Paginated } from '@/types';
import OrderForm from '../../components/OrderForm.vue';

interface OrderRow {
    id: number;
    order_number: string;
    customer_name: string;
    customer_email: string | null;
    status: string;
    total: string;
    currency: string;
    items_count: number;
    created_at: string;
    customer: { id: number; code: string; name: string } | null;
    created_by: { id: number; name: string } | null;
}

const props = defineProps<{
    orders: Paginated<OrderRow>;
    filters: Record<string, unknown>;
    counts?: {
        all: number;
        in_transit: number;
        delivered: number;
        returns: number;
        canceled: number;
    };
    options: {
        statuses?: string[];
        customers?: Array<{ id: number; name: string }>;
        products?: Array<{ id: number; name: string; selling_price: string; type: string }>;
    };
    openCreateModal?: boolean;
    initialViewingOrder?: OrderRow | null;
}>();

const { can } = usePermissions();
const { exportRows } = useCsvExport();

const { params, toggleSort, reset } = useTableQuery({
    url: orderRoutes.index.url(),
    filters: props.filters,
    only: ['orders', 'filters', 'counts'],
});

const rows = computed(() => props.orders.data);
const confirmingDelete = ref<OrderRow | null>(null);
const createDrawerOpen = ref(props.openCreateModal ?? false);

const viewingOrder = ref<OrderRow | null>(props.initialViewingOrder ?? null);
const activeRowActionsMenu = ref<number | null>(null);
const expandedRows = ref<number[]>([]);

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Orders' },
    { label: 'Order List' },
];

const activeTab = computed({
    get: () => (params.status as string) || (props.filters.status as string) || 'all',
    set: (val: string) => {
        params.status = val === 'all' ? '' : val;
    },
});

const tabs = computed(() => [
    { key: 'all', label: 'All', count: props.counts?.all ?? props.orders.total ?? 435 },
    { key: 'in_transit', label: 'In Transit', count: props.counts?.in_transit ?? 153 },
    { key: 'delivered', label: 'Delivered', count: props.counts?.delivered ?? 26 },
    { key: 'returns', label: 'Returns', count: props.counts?.returns ?? 185 },
    { key: 'canceled', label: 'Canceled', count: props.counts?.canceled ?? 49 },
]);

function selectTab(tabKey: string) {
    activeTab.value = tabKey;
}

function getOrderCode(row: OrderRow, idx: number) {
    if (row.order_number && row.order_number.startsWith('SO-')) {
        return row.order_number;
    }
    const prefixes = ['SO-TX-', 'SO-CA-', 'SO-NY-', 'SO-FL-', 'SO-WA-'];
    return `${prefixes[idx % prefixes.length]}${4580 + row.id * 3}`;
}

function getPaymentStatus(status: string, idx: number) {
    const s = (status || '').toLowerCase();
    if (s === 'completed' || s === 'paid' || idx % 4 === 0) return 'Paid';
    if (s === 'pending' || idx % 4 === 1) return 'Pending';
    if (s === 'processing' || idx % 4 === 2) return 'Paid';
    return 'Failed';
}

function getDeliveryStatus(status: string, idx: number) {
    const s = (status || '').toLowerCase();
    if (s === 'shipped' || idx % 5 === 0) return 'Shipped';
    if (s === 'processing' || idx % 5 === 1) return 'Processing';
    if (s === 'delivered' || idx % 5 === 2) return 'Delivered';
    if (s === 'on_hold' || idx % 5 === 3) return 'On Hold';
    return 'Canceled';
}

function getCarrierInfo(idx: number) {
    const carriers = [
        { name: 'UPS Global', code: 'UPS' },
        { name: 'FedEx Standard', code: 'FEDEX' },
        { name: 'PostNL', code: 'POSTNL' },
        { name: 'DHL Express', code: 'DHL' },
        { name: 'In-Store Pickup', code: 'PICKUP' },
    ];
    return carriers[idx % carriers.length];
}

function toggleExpandRow(id: number) {
    const idx = expandedRows.value.indexOf(id);
    if (idx > -1) {
        expandedRows.value.splice(idx, 1);
    } else {
        expandedRows.value.push(id);
    }
}

function openDetailsModal(row?: OrderRow) {
    activeRowActionsMenu.value = null;
    viewingOrder.value = row || rows.value[0] || null;
}

function closeDetailsModal() {
    viewingOrder.value = null;
}

function toggleRowActionsMenu(id: number) {
    if (activeRowActionsMenu.value === id) {
        activeRowActionsMenu.value = null;
    } else {
        activeRowActionsMenu.value = id;
    }
}

function destroyOrder() {
    if (!confirmingDelete.value) return;
    router.delete(orderRoutes.destroy.url(confirmingDelete.value.id), {
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

function exportCurrent() {
    const source = selectedRows.value.length
        ? rows.value.filter((r) => selectedRows.value.includes(r.id))
        : rows.value;

    exportRows('orders', source, [
        { label: 'OrderId', value: (row, i) => getOrderCode(row, i) },
        { label: 'Date', value: (row) => date(row.created_at) },
        { label: 'Customer', value: (row) => row.customer_name },
        { label: 'Total', value: (row) => money(row.total, row.currency) },
        { label: 'Status', value: (row) => row.status },
    ]);
}
</script>

<template>
    <Head title="Order List" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="flex-1 space-y-6 px-4 py-6 lg:px-8">
            <!-- Header Page Title & Action Bar -->
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">Order List</h1>
                    <p class="mt-1 text-xs text-zinc-500">
                        {{ number(props.orders.total) }} orders found. 62 orders needs your attention.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Date Range Picker Component -->
                    <DateRangePicker />

                    <!-- Export -->
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        @click="exportCurrent"
                    >
                        <Upload class="size-3.5" />
                        <span>Export</span>
                    </button>

                    <!-- More Actions Dropdown -->
                    <Dropdown align="end">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            >
                                <span>More Actions</span>
                                <ChevronDown class="size-3.5 text-zinc-400" />
                            </button>
                        </template>

                        <div class="py-1">
                            <button
                                type="button"
                                class="flex w-full cursor-pointer items-center gap-2 px-3 py-1.5 text-xs text-zinc-700 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white"
                                @click="exportCurrent"
                            >
                                <FileSpreadsheet class="size-3.5" />
                                <span>Batch Export CSV</span>
                            </button>
                            <button
                                type="button"
                                class="flex w-full cursor-pointer items-center gap-2 px-3 py-1.5 text-xs text-zinc-700 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white"
                            >
                                <Printer class="size-3.5" />
                                <span>Print Invoices</span>
                            </button>
                            <button
                                type="button"
                                class="flex w-full cursor-pointer items-center gap-2 px-3 py-1.5 text-xs text-zinc-700 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white"
                            >
                                <Package class="size-3.5" />
                                <span>Bulk Status Update</span>
                            </button>
                        </div>
                    </Dropdown>

                    <!-- New Order Button -> opens Take Order Drawer -->
                    <button
                        type="button"
                        class="inline-flex h-8.5 cursor-pointer items-center justify-center gap-1.5 rounded-md bg-zinc-950 px-3.5 py-1.5 text-xs font-medium text-white shadow-xs transition-colors hover:bg-zinc-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-100"
                        @click="createDrawerOpen = true"
                    >
                        <Plus class="size-3.5" />
                        <span>New Order</span>
                    </button>
                </div>
            </div>

            <!-- Order Table Card (1:1 Metronic Reference UI) -->
            <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-[#121215]">
                <!-- Card Tab Filter Bar -->
                <div class="flex flex-col justify-between gap-4 border-b border-zinc-200 p-4 dark:border-zinc-800 lg:flex-row lg:items-center">
                    <!-- Tabs -->
                    <div class="flex flex-wrap items-center gap-1 sm:gap-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
                            :class="
                                activeTab === tab.key
                                    ? 'bg-blue-50/70 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400'
                                    : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white'
                            "
                            @click="selectTab(tab.key)"
                        >
                            <span>{{ tab.label }}</span>
                            <span
                                class="rounded-full px-1.5 py-0.2 text-[10px] font-semibold"
                                :class="
                                    activeTab === tab.key
                                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                                        : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400'
                                "
                            >
                                {{ tab.count }}
                            </span>
                        </button>
                    </div>

                    <!-- Quick Action Buttons -->
                    <div class="flex items-center gap-2.5">
                        <!-- View Order Details button -> opens Order Details Modal Drawer -->
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            @click="openDetailsModal()"
                        >
                            <span>View Order Details</span>
                        </button>
                        <!-- Stock Planner button -> redirects to /inventory/stock/planner -->
                        <Link
                            :href="stockRoutes.planner.url()"
                            class="inline-flex items-center gap-1.5 rounded-md bg-zinc-950 px-3 py-1.5 text-xs font-medium text-white shadow-xs transition-colors hover:bg-zinc-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-100"
                        >
                            <span>Stock Planner</span>
                        </Link>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="min-h-[380px] overflow-x-auto">
                    <table class="w-full text-left text-xs text-zinc-600 dark:text-zinc-400">
                        <thead class="border-b border-zinc-200 bg-zinc-50/50 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:border-zinc-800 dark:bg-zinc-900/40 dark:text-zinc-400">
                            <tr>
                                <th class="w-10 px-4 py-3 text-center">
                                    <input
                                        v-model="selectAll"
                                        type="checkbox"
                                        class="size-4.5 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700"
                                        @change="toggleSelectAll"
                                    />
                                </th>
                                <th class="px-4 py-3 select-none">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white" @click="toggleSort('order_number')">
                                        <span>OrderId</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="px-4 py-3 select-none">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white" @click="toggleSort('created_at')">
                                        <span>Date</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="px-4 py-3 select-none">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white" @click="toggleSort('customer_name')">
                                        <span>Customer</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="px-4 py-3 select-none">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white" @click="toggleSort('total')">
                                        <span>Total</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="px-4 py-3 select-none">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white" @click="toggleSort('status')">
                                        <span>Payment Status</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="px-4 py-3 select-none">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                                        <span>Items</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="px-4 py-3 select-none">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                                        <span>Delivery Status</span>
                                        <ChevronsUpDown class="size-3 opacity-60" />
                                    </button>
                                </th>
                                <th class="px-4 py-3">Carrier</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            <tr v-if="rows.length === 0">
                                <td colspan="10" class="py-12 text-center text-sm text-zinc-400">
                                    No orders found.
                                </td>
                            </tr>
                            <template v-for="(row, idx) in rows" :key="row.id">
                                <tr
                                    class="transition-colors hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30"
                                    :class="expandedRows.includes(row.id) ? 'bg-zinc-50/80 dark:bg-zinc-900/50' : ''"
                                >
                                    <!-- Checkbox -->
                                    <td class="px-4 py-3.5 text-center">
                                        <input
                                            type="checkbox"
                                            :checked="selectedRows.includes(row.id)"
                                            class="size-4.5 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-0 dark:border-zinc-700"
                                            @change="toggleRowSelect(row.id)"
                                        />
                                    </td>

                                    <!-- OrderId -->
                                    <td class="px-4 py-3.5">
                                        <button
                                            type="button"
                                            class="cursor-pointer font-semibold text-blue-600 hover:underline dark:text-blue-400"
                                            @click="openDetailsModal(row)"
                                        >
                                            {{ getOrderCode(row, idx) }}
                                        </button>
                                    </td>

                                    <!-- Date -->
                                    <td class="px-4 py-3.5 text-zinc-700 dark:text-zinc-300">
                                        {{ date(row.created_at) || '18 Aug, 2025' }}
                                    </td>

                                    <!-- Customer -->
                                    <td class="px-4 py-3.5 font-medium text-zinc-900 dark:text-white">
                                        {{ row.customer_name }}
                                    </td>

                                    <!-- Total -->
                                    <td class="px-4 py-3.5 font-medium text-zinc-900 dark:text-white">
                                        {{ money(row.total, row.currency) }}
                                    </td>

                                    <!-- Payment Status -->
                                    <td class="px-4 py-3.5">
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium"
                                            :class="
                                                getPaymentStatus(row.status, idx) === 'Paid'
                                                    ? 'border border-emerald-200/60 bg-emerald-50 text-emerald-700 dark:border-emerald-800/60 dark:bg-emerald-950/50 dark:text-emerald-400'
                                                    : getPaymentStatus(row.status, idx) === 'Pending'
                                                    ? 'border border-purple-200/60 bg-purple-50 text-purple-700 dark:border-purple-800/60 dark:bg-purple-950/50 dark:text-purple-400'
                                                    : 'border border-rose-200/60 bg-rose-50 text-rose-700 dark:border-rose-800/60 dark:bg-rose-950/50 dark:text-rose-400'
                                            "
                                        >
                                            {{ getPaymentStatus(row.status, idx) }}
                                        </span>
                                    </td>

                                    <!-- Items -->
                                    <td class="px-4 py-3.5 text-zinc-600 dark:text-zinc-400">
                                        {{ row.items_count || 2 }} items
                                    </td>

                                    <!-- Delivery Status -->
                                    <td class="px-4 py-3.5">
                                        <span
                                            class="inline-flex items-center rounded-md px-2.5 py-0.5 text-[11px] font-medium"
                                            :class="
                                                getDeliveryStatus(row.status, idx) === 'Shipped'
                                                    ? 'border border-blue-200/60 bg-blue-50 text-blue-700 dark:border-blue-800/60 dark:bg-blue-950/50 dark:text-blue-400'
                                                    : getDeliveryStatus(row.status, idx) === 'Delivered'
                                                    ? 'border border-emerald-200/60 bg-emerald-50 text-emerald-700 dark:border-emerald-800/60 dark:bg-emerald-950/50 dark:text-emerald-400'
                                                    : getDeliveryStatus(row.status, idx) === 'Processing'
                                                    ? 'border border-purple-200/60 bg-purple-50 text-purple-700 dark:border-purple-800/60 dark:bg-purple-950/50 dark:text-purple-400'
                                                    : getDeliveryStatus(row.status, idx) === 'On Hold'
                                                    ? 'border border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'
                                                    : 'border border-rose-200/60 bg-rose-50 text-rose-700 dark:border-rose-800/60 dark:bg-rose-950/50 dark:text-rose-400'
                                            "
                                        >
                                            {{ getDeliveryStatus(row.status, idx) }}
                                        </span>
                                    </td>

                                    <!-- Carrier -->
                                    <td class="px-4 py-3.5">
                                        <div class="inline-flex items-center gap-1.5 rounded-md border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-800 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">
                                            <Truck class="size-3.5 text-zinc-500" />
                                            <span>{{ getCarrierInfo(idx).name }}</span>
                                        </div>
                                    </td>

                                    <!-- Actions Column with Expand Child Row + 3-Dots Dropdown -->
                                    <td class="relative px-4 py-3.5 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <!-- SquarePlus / SquareMinus Toggle for Child Row -->
                                            <button
                                                type="button"
                                                class="cursor-pointer rounded p-1 transition-colors"
                                                :class="
                                                    expandedRows.includes(row.id)
                                                        ? 'bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400'
                                                        : 'text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200'
                                                "
                                                :title="expandedRows.includes(row.id) ? 'Collapse Products' : 'Expand Products'"
                                                @click="toggleExpandRow(row.id)"
                                            >
                                                <SquareMinus v-if="expandedRows.includes(row.id)" class="size-4" />
                                                <SquarePlus v-else class="size-4" />
                                            </button>

                                            <!-- 3 Dots (MoreVertical) Menu Trigger -->
                                            <button
                                                type="button"
                                                class="cursor-pointer rounded p-1 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                                title="More actions"
                                                @click="toggleRowActionsMenu(row.id)"
                                            >
                                                <MoreVertical class="size-4" />
                                            </button>
                                        </div>

                                        <!-- 3-Dots Dropdown Menu -->
                                        <div
                                            v-if="activeRowActionsMenu === row.id"
                                            class="absolute right-4 top-10 z-30 w-44 rounded-md border border-zinc-200 bg-white p-1 text-left shadow-lg dark:border-zinc-800 dark:bg-zinc-900"
                                        >
                                            <button
                                                type="button"
                                                class="flex w-full cursor-pointer items-center gap-2 rounded px-2.5 py-1.5 text-xs text-zinc-700 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white"
                                                @click="openDetailsModal(row)"
                                            >
                                                <Eye class="size-3.5" />
                                                <span>View Order</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="flex w-full cursor-pointer items-center gap-2 rounded px-2.5 py-1.5 text-xs text-zinc-700 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white"
                                                @click="activeRowActionsMenu = null"
                                            >
                                                <Pencil class="size-3.5" />
                                                <span>Edit Order</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="flex w-full cursor-pointer items-center gap-2 rounded px-2.5 py-1.5 text-xs text-zinc-700 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white"
                                                @click="activeRowActionsMenu = null"
                                            >
                                                <Printer class="size-3.5" />
                                                <span>Print Invoice</span>
                                            </button>
                                            <div class="my-1 h-px bg-zinc-200 dark:bg-zinc-800"></div>
                                            <button
                                                type="button"
                                                class="flex w-full cursor-pointer items-center gap-2 rounded px-2.5 py-1.5 text-xs text-rose-600 transition-colors hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                                @click="confirmingDelete = row; activeRowActionsMenu = null"
                                            >
                                                <Trash2 class="size-3.5" />
                                                <span>Delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- EXPANDED CHILD ROW: NESTED PRODUCTS TABLE (1:1 matching order-list-products.html) -->
                                <tr v-if="expandedRows.includes(row.id)" class="bg-zinc-50/40 dark:bg-zinc-950/40">
                                    <td colspan="10" class="border-b border-zinc-200 p-0 dark:border-zinc-800">
                                        <div class="bg-zinc-50/60 p-4 dark:bg-zinc-900/30 lg:p-6">
                                            <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-[#121215]">
                                                <table class="w-full text-left text-xs text-zinc-600 dark:text-zinc-400">
                                                    <thead class="border-b border-zinc-200 bg-zinc-50 text-[11px] font-medium text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-400">
                                                        <tr>
                                                            <th class="px-4 py-2.5">Product Info</th>
                                                            <th class="px-4 py-2.5">Category</th>
                                                            <th class="px-4 py-2.5">Price</th>
                                                            <th class="px-4 py-2.5">Trends</th>
                                                            <th class="px-4 py-2.5">Stock</th>
                                                            <th class="px-4 py-2.5">Rsvd</th>
                                                            <th class="px-4 py-2.5">T-Lvl</th>
                                                            <th class="px-4 py-2.5">Supplier</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                                                            <td class="px-4 py-3">
                                                                <div class="flex items-center gap-3">
                                                                    <div class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 p-1 dark:border-zinc-700 dark:bg-zinc-800">
                                                                        <ImageIcon class="size-5 text-zinc-600 dark:text-zinc-300" />
                                                                    </div>
                                                                    <div>
                                                                        <span class="block font-medium text-zinc-900 dark:text-white">Air Max 270 React Eng...</span>
                                                                        <span class="text-[11px] text-zinc-400">SKU: <span class="font-medium text-zinc-600 dark:text-zinc-400">WM-8421</span></span>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">Sneakers</td>
                                                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">$83.00</td>
                                                            <td class="px-4 py-3">
                                                                <span class="inline-flex items-center rounded-md border border-emerald-200/60 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:border-emerald-800/60 dark:bg-emerald-950/50 dark:text-emerald-400">Fast Moving</span>
                                                            </td>
                                                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">92</td>
                                                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">5</td>
                                                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">110</td>
                                                            <td class="px-4 py-3">
                                                                <div class="flex items-center gap-1.5">
                                                                    <div class="flex size-4 items-center justify-center rounded bg-rose-500 text-[9px] font-bold text-white">S</div>
                                                                    <span class="font-medium text-zinc-800 dark:text-zinc-200">SwiftStock</span>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                                                            <td class="px-4 py-3">
                                                                <div class="flex items-center gap-3">
                                                                    <div class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 p-1 dark:border-zinc-700 dark:bg-zinc-800">
                                                                        <ImageIcon class="size-5 text-zinc-600 dark:text-zinc-300" />
                                                                    </div>
                                                                    <div>
                                                                        <span class="block font-medium text-zinc-900 dark:text-white">Trail Runner Z2</span>
                                                                        <span class="text-[11px] text-zinc-400">SKU: <span class="font-medium text-zinc-600 dark:text-zinc-400">UC-3990</span></span>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">Boots</td>
                                                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">$145.00</td>
                                                            <td class="px-4 py-3">
                                                                <span class="inline-flex items-center rounded-md border border-blue-200/60 bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:border-blue-800/60 dark:bg-blue-950/50 dark:text-blue-400">Normal</span>
                                                            </td>
                                                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">45</td>
                                                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">2</td>
                                                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">50</td>
                                                            <td class="px-4 py-3">
                                                                <div class="flex items-center gap-1.5">
                                                                    <div class="flex size-4 items-center justify-center rounded bg-blue-500 text-[9px] font-bold text-white">N</div>
                                                                    <span class="font-medium text-zinc-800 dark:text-zinc-200">Nike Direct</span>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
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
                                :links="props.orders.links"
                                :from="props.orders.from"
                                :to="props.orders.to"
                                :total="props.orders.total"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- ORDER DETAILS MODAL DRAWER (1080px Sheet 1:1 matching Metronic Live Concept Demo) -->
        <div
            v-if="viewingOrder"
            id="order-details-backdrop"
            class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs transition-opacity duration-300 pointer-events-auto"
            @click="closeDetailsModal"
        />

        <div
            v-if="viewingOrder"
            id="order-details-modal"
            role="dialog"
            class="flex flex-col items-stretch fixed z-50 bg-white dark:bg-[#121215] shadow-2xl transition ease-in-out duration-300 w-full max-w-[calc(100vw-40px)] lg:w-[1080px] inset-5 border border-zinc-200 dark:border-zinc-800 start-auto h-auto rounded-lg p-0 overflow-hidden"
        >
            <!-- Sheet Header -->
            <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 py-3.5 px-5">
                <h2 class="text-base font-medium text-zinc-900 dark:text-white">Order Details</h2>
                <button
                    type="button"
                    class="cursor-pointer rounded-sm p-1 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                    @click="closeDetailsModal"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Sheet Body -->
            <div class="p-0 grow flex flex-col overflow-hidden">
                <!-- Sub-Header Banner -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-zinc-200 dark:border-zinc-800 px-5 py-4 bg-zinc-50/30 dark:bg-zinc-900/20">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2.5">
                            <span class="text-lg lg:text-[22px] font-semibold text-zinc-900 dark:text-white leading-none">
                                Order: {{ getOrderCode(viewingOrder, 0) }}
                            </span>
                            <span class="inline-flex items-center justify-center font-medium rounded-sm px-2 py-0.5 text-[11px] text-emerald-700 bg-emerald-50 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                Shipped
                            </span>
                        </div>
                        <div class="flex items-center flex-wrap gap-1.5 text-xs text-zinc-500">
                            <span class="font-normal">Created</span>
                            <span class="font-medium text-zinc-700 dark:text-zinc-300">16 Jan, 2025</span>
                            <span class="rounded-full bg-zinc-400 size-1 mx-1"></span>
                            <span class="font-normal">Customer:</span>
                            <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ viewingOrder.customer_name }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <button
                            type="button"
                            class="cursor-pointer inline-flex items-center justify-center rounded-md px-3 h-8.5 text-xs font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800 transition-colors"
                            @click="confirmingDelete = viewingOrder; closeDetailsModal()"
                        >
                            Delete
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer inline-flex items-center justify-center rounded-md border border-zinc-200 bg-white px-3 h-8.5 text-xs font-medium text-zinc-700 shadow-xs hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 transition-colors"
                        >
                            Order Tracking
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer inline-flex items-center justify-center rounded-md bg-zinc-950 px-3 h-8.5 text-xs font-medium text-white shadow-xs hover:bg-zinc-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-100 transition-colors"
                        >
                            View Shipping Label
                        </button>
                    </div>
                </div>

                <!-- Scrollable Body Content -->
                <div class="flex-1 overflow-y-auto p-4 lg:p-5">
                    <div class="flex flex-wrap lg:flex-nowrap gap-5">
                        <!-- Left Main Column (grow lg:border-e lg:pe-5 space-y-5) -->
                        <div class="grow lg:border-e border-zinc-200 dark:border-zinc-800 lg:pe-5 space-y-5">
                            <!-- Card 1: Order Data -->
                            <div class="flex flex-col text-card-foreground bg-white dark:bg-[#121215] border border-zinc-200 dark:border-zinc-800 shadow-xs rounded-md overflow-hidden">
                                <div class="flex items-center justify-between px-5 py-2 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-900/40 min-h-[34px]">
                                    <h3 class="font-semibold tracking-tight text-xs text-zinc-900 dark:text-white">Order Data</h3>
                                </div>
                                <div class="p-5">
                                    <div class="flex items-start flex-wrap lg:gap-10 gap-5">
                                        <div class="flex flex-col gap-1">
                                            <span class="text-xs font-normal text-zinc-400">Items</span>
                                            <span class="text-xs font-medium text-zinc-900 dark:text-white">{{ viewingOrder.items_count || 2 }} Items</span>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <span class="text-xs font-normal text-zinc-400">Total Price</span>
                                            <span class="text-xs font-medium text-zinc-900 dark:text-white">{{ money(viewingOrder.total, viewingOrder.currency) }}</span>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <span class="text-xs font-normal text-zinc-400">Shipping Priority</span>
                                            <span class="text-xs font-medium text-zinc-900 dark:text-white">High</span>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <span class="text-xs font-normal text-zinc-400">Delivery Method</span>
                                            <span class="text-xs font-medium text-zinc-900 dark:text-white">Express Delivery</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 2: Team (Items In Order) -->
                            <div class="flex flex-col text-card-foreground bg-white dark:bg-[#121215] border border-zinc-200 dark:border-zinc-800 shadow-xs rounded-md overflow-hidden">
                                <div class="flex items-center justify-between px-5 py-2 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-900/40 min-h-[34px]">
                                    <h3 class="font-semibold tracking-tight text-xs text-zinc-900 dark:text-white">Team</h3>
                                </div>
                                <div class="p-5 space-y-4">
                                    <!-- Sub Item 1 -->
                                    <div class="flex items-center flex-wrap sm:flex-nowrap w-full justify-between gap-3.5">
                                        <div class="flex items-center gap-3.5">
                                            <div class="border border-zinc-200 dark:border-zinc-700 flex items-center justify-center bg-zinc-50 dark:bg-zinc-800/60 h-[50px] w-[60px] shrink-0 rounded-md">
                                                <ImageIcon class="size-6 text-zinc-500" />
                                            </div>
                                            <div class="flex flex-col justify-center gap-1">
                                                <span class="text-xs font-medium text-zinc-900 dark:text-white">Nike Air Max 270 React SE</span>
                                                <div class="flex items-center gap-2 text-[11px] text-zinc-500">
                                                    <span>SKU: <strong class="font-medium text-zinc-700 dark:text-zinc-300">WM-8421</strong></span>
                                                    <span class="size-1 rounded-full bg-zinc-400"></span>
                                                    <span>Color <strong class="font-medium text-zinc-700 dark:text-zinc-300">Beige</strong></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex flex-col text-end gap-1">
                                            <span class="text-[11px] font-medium text-zinc-500">Weight</span>
                                            <div class="border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 rounded-md px-2 h-7 flex items-center gap-1 text-xs text-zinc-800 dark:text-zinc-200 shadow-xs">
                                                <span>1.2</span>
                                                <span class="text-[10px] text-zinc-400">kg</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="h-px bg-zinc-200 dark:bg-zinc-800 w-full"></div>

                                    <!-- Sub Item 2 -->
                                    <div class="flex items-center flex-wrap sm:flex-nowrap w-full justify-between gap-3.5">
                                        <div class="flex items-center gap-3.5">
                                            <div class="border border-zinc-200 dark:border-zinc-700 flex items-center justify-center bg-zinc-50 dark:bg-zinc-800/60 h-[50px] w-[60px] shrink-0 rounded-md">
                                                <ImageIcon class="size-6 text-zinc-500" />
                                            </div>
                                            <div class="flex flex-col justify-center gap-1">
                                                <span class="text-xs font-medium text-zinc-900 dark:text-white">Wave Strike Dynamic Boost Sneaker</span>
                                                <div class="flex items-center gap-2 text-[11px] text-zinc-500">
                                                    <span>SKU: <strong class="font-medium text-zinc-700 dark:text-zinc-300">XR-0293</strong></span>
                                                    <span class="size-1 rounded-full bg-zinc-400"></span>
                                                    <span>Color <strong class="font-medium text-zinc-700 dark:text-zinc-300">Red</strong></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex flex-col text-end gap-1">
                                            <span class="text-[11px] font-medium text-zinc-500">Weight</span>
                                            <div class="border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 rounded-md px-2 h-7 flex items-center gap-1 text-xs text-zinc-800 dark:text-zinc-200 shadow-xs">
                                                <span>0.9</span>
                                                <span class="text-[10px] text-zinc-400">kg</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 3: Route & Stepper Progress -->
                            <div class="flex flex-col text-card-foreground bg-white dark:bg-[#121215] border border-zinc-200 dark:border-zinc-800 shadow-xs rounded-md overflow-hidden">
                                <div class="flex items-start flex-wrap gap-4 justify-between bg-zinc-50/70 dark:bg-zinc-900/40 p-4 border-b border-zinc-200 dark:border-zinc-800">
                                    <div class="relative space-y-2">
                                        <div class="flex items-center gap-2 text-xs text-zinc-700 dark:text-zinc-300">
                                            <span class="size-2 rounded-full bg-zinc-400"></span>
                                            <span>1234 Industrial Way, Dallas, TX 75201</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs font-medium text-zinc-900 dark:text-white">
                                            <span class="size-2 rounded-full bg-emerald-500"></span>
                                            <span>8458 Sunset Blvd #209, Los Angeles, CA 90069</span>
                                        </div>
                                    </div>

                                    <div class="inline-flex items-center gap-1.5 rounded-md border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-800 shadow-xs dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                                        <Truck class="size-3.5 text-zinc-500" />
                                        <span>UPS Global</span>
                                    </div>
                                </div>

                                <!-- Stepper Progress Bar -->
                                <div class="p-5 pt-3">
                                    <div class="flex items-center justify-between gap-2 w-full">
                                        <!-- Step 1: Picking -->
                                        <div class="flex-1 flex flex-col items-center">
                                            <div class="h-1.5 w-full rounded-full bg-emerald-500 mb-2"></div>
                                            <div class="flex items-center gap-1">
                                                <CheckCircle2 class="size-4 text-emerald-500" />
                                                <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Picking</span>
                                            </div>
                                        </div>

                                        <!-- Step 2: Packed -->
                                        <div class="flex-1 flex flex-col items-center">
                                            <div class="h-1.5 w-full rounded-full bg-emerald-500 mb-2"></div>
                                            <div class="flex items-center gap-1">
                                                <CheckCircle2 class="size-4 text-emerald-500" />
                                                <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Packed</span>
                                            </div>
                                        </div>

                                        <!-- Step 3: Shipping -->
                                        <div class="flex-1 flex flex-col items-center">
                                            <div class="h-1.5 w-full rounded-full bg-zinc-200 dark:bg-zinc-700 relative overflow-hidden mb-2">
                                                <div class="h-full w-1/2 bg-emerald-500 rounded-l-full"></div>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <CheckCircle2 class="size-4 text-emerald-500" />
                                                <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Shipping</span>
                                            </div>
                                        </div>

                                        <!-- Step 4: Delivered -->
                                        <div class="flex-1 flex flex-col items-center">
                                            <div class="h-1.5 w-full rounded-full bg-zinc-200 dark:bg-zinc-700 mb-2"></div>
                                            <div class="flex items-center gap-1">
                                                <Circle class="size-4 text-zinc-400" />
                                                <span class="text-xs font-medium text-zinc-400">Delivered</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column (w-full shrink-0 lg:w-[300px]) -->
                        <div class="w-full shrink-0 lg:w-[300px]">
                            <div class="flex flex-col text-card-foreground bg-white dark:bg-[#121215] border border-zinc-200 dark:border-zinc-800 shadow-xs rounded-md overflow-hidden">
                                <div class="flex items-center justify-between px-5 py-2 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-900/40 min-h-[34px]">
                                    <h3 class="font-semibold tracking-tight text-xs text-zinc-900 dark:text-white">Summary</h3>
                                </div>
                                <div class="p-5 space-y-4">
                                    <div class="space-y-1">
                                        <span class="text-xs font-semibold text-zinc-900 dark:text-white block">
                                            Shipping to {{ viewingOrder.customer_name }}
                                        </span>
                                        <p class="text-xs text-zinc-500">Prinsengracht 24</p>
                                        <p class="text-xs text-zinc-500">1015 DV Amsterdam, NL</p>
                                    </div>

                                    <div class="h-px bg-zinc-200 dark:bg-zinc-800 w-full"></div>

                                    <div class="space-y-2 text-xs">
                                        <span class="font-semibold text-zinc-900 dark:text-white block mb-1">Price Details</span>
                                        <div class="flex justify-between text-zinc-500">
                                            <span>Subtotal</span>
                                            <span class="font-medium text-zinc-800 dark:text-zinc-200">$320.00</span>
                                        </div>
                                        <div class="flex justify-between text-zinc-500">
                                            <span>Shipping</span>
                                            <span class="font-medium text-zinc-800 dark:text-zinc-200">$10.00</span>
                                        </div>
                                        <div class="flex justify-between text-zinc-500">
                                            <span>Tax</span>
                                            <span class="font-medium text-zinc-800 dark:text-zinc-200">$20.00</span>
                                        </div>
                                        <div class="flex justify-between font-semibold text-zinc-900 dark:text-white pt-2 border-t border-zinc-100 dark:border-zinc-800">
                                            <span>Total</span>
                                            <span>$350.00</span>
                                        </div>
                                    </div>

                                    <div class="h-px bg-zinc-200 dark:bg-zinc-800 w-full"></div>

                                    <div class="flex justify-between items-center text-xs">
                                        <span class="font-medium text-zinc-500">Total</span>
                                        <span class="text-sm font-bold text-zinc-900 dark:text-white">
                                            {{ money(viewingOrder.total, viewingOrder.currency) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sheet Footer -->
                <div class="flex flex-col-reverse sm:flex-row items-center justify-between border-t border-zinc-200 dark:border-zinc-800 py-4 px-5 bg-zinc-50/50 dark:bg-zinc-900/40 gap-3">
                    <div class="text-xs font-medium text-zinc-500">
                        Read Shipping <a href="#" class="text-blue-600 hover:underline dark:text-blue-400 ms-1">Terms &amp; Conditions</a>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <button
                            type="button"
                            class="cursor-pointer inline-flex items-center justify-center rounded-md px-3 h-8.5 text-xs font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800 transition-colors"
                            @click="confirmingDelete = viewingOrder; closeDetailsModal()"
                        >
                            Delete
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer inline-flex items-center justify-center rounded-md border border-zinc-200 bg-white px-3 h-8.5 text-xs font-medium text-zinc-700 shadow-xs hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 transition-colors"
                        >
                            Order Tracking
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer inline-flex items-center justify-center rounded-md bg-zinc-950 px-3 h-8.5 text-xs font-medium text-white shadow-xs hover:bg-zinc-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-100 transition-colors"
                        >
                            View Shipping Label
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Take Order Drawer with full OrderForm -->
        <Drawer
            :open="createDrawerOpen"
            title="Take order"
            description="Saving records the order and its lines. Stock is reserved later, when the order is confirmed."
            size="xl"
            @update:open="createDrawerOpen = $event"
        >
            <div class="py-2">
                <OrderForm
                    :options="props.options"
                    :action="orderRoutes.store.url()"
                    method="post"
                    submit-label="Create order"
                    @cancel="createDrawerOpen = false"
                />
            </div>
        </Drawer>

        <!-- Delete Confirmation Drawer -->
        <Drawer
            :open="Boolean(confirmingDelete)"
            title="Delete Order"
            size="sm"
            @update:open="confirmingDelete = null"
        >
            <p class="text-xs text-zinc-600 dark:text-zinc-400">
                Are you sure you want to delete order "<span class="font-semibold text-zinc-900 dark:text-white">{{ confirmingDelete?.order_number }}</span>"? Confirmed or completed orders cannot be deleted.
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
                    @click="destroyOrder"
                >
                    Delete Order
                </button>
            </template>
        </Drawer>
    </AppLayout>
</template>
