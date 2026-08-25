<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import {
    ArrowDownRightIcon,
    ArrowUpRightIcon,
    BoxesIcon,
    PackageIcon,
    TriangleAlertIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import SparklineChart from '@/components/charts/SparklineChart.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, money, number } from '@/lib/format';
import { humanize } from '@/lib/status';
import orders from '@/routes/inventory/orders';
import products from '@/routes/inventory/products';
import stock from '@/routes/inventory/stock';

interface Series {
    labels: string[];
    series: Record<string, number[]>;
}

interface Statistics {
    totals: {
        products: number;
        categories: number;
        suppliers: number;
        active_suppliers: number;
        inventory_on_hand: number;
        inventory_reserved: number;
        low_stock_products: number;
        orders: number;
    };
    revenue: {
        window_days: number;
        current: number;
        previous: number;
        delta_percent: number | null;
    };
    sales_series: Series;
    movement_series: Series;
    orders_by_status: Record<string, number>;
    movement_summary: {
        inbound_units: number;
        outbound_units: number;
        by_type: Record<string, number>;
    };
    low_stock_items: Array<{
        id: number;
        product_id: number;
        quantity_on_hand: number;
        quantity_reserved: number;
        product: { id: number; name: string; sku: string | null; low_stock_threshold: number };
        variant: { id: number; sku: string; name: string } | null;
    }>;
    recent_orders: Array<{
        id: number;
        order_number: string;
        customer_name: string;
        status: string;
        total: string;
        currency: string;
        items_count: number;
        created_at: string;
    }>;
    recent_movements: Array<{
        id: number;
        type: string;
        quantity: number;
        created_at: string;
        product: { id: number; name: string } | null;
        user: { id: number; name: string } | null;
    }>;
}

const props = defineProps<{ statistics?: Statistics }>();

const breadcrumbs = [{ label: 'Dashboards' }, { label: 'Default' }];

const salesSeries = computed(() => {
    const stats = props.statistics;

    if (!stats) {
        return [];
    }

    return [
        { name: 'Revenue', data: stats.sales_series.series.revenue ?? [] },
        { name: 'Orders', data: stats.sales_series.series.orders ?? [] },
    ];
});

const movementSeries = computed(() => {
    const stats = props.statistics;

    if (!stats) {
        return [];
    }

    return [
        { name: 'Units in', data: stats.movement_series.series.inbound ?? [] },
        { name: 'Units out', data: stats.movement_series.series.outbound ?? [] },
    ];
});

const statusLabels = computed(() =>
    Object.keys(props.statistics?.orders_by_status ?? {}).map((status) =>
        humanize(status),
    ),
);

const statusValues = computed(() =>
    Object.values(props.statistics?.orders_by_status ?? {}),
);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader title="Store overview" :breadcrumbs="breadcrumbs" />

        <Deferred data="statistics">
            <template #fallback>
                <div class="space-y-6">
                    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        <div
                            v-for="index in 3"
                            :key="index"
                            class="h-64 animate-pulse rounded-xl bg-muted/40"
                        />
                    </div>
                    <div class="h-80 animate-pulse rounded-xl bg-muted/40" />
                </div>
            </template>

            <div v-if="statistics" class="space-y-6">
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <StatCard
                        label="Revenue"
                        :value="money(statistics.revenue.current)"
                        :delta="statistics.revenue.delta_percent"
                        :hint="`Last ${statistics.revenue.window_days} days, cancelled orders excluded`"
                    >
                        <SparklineChart
                            name="Revenue"
                            :data="statistics.sales_series.series.revenue ?? []"
                            prefix="$"
                        />
                    </StatCard>

                    <StatCard
                        label="Stock on hand"
                        :value="number(statistics.totals.inventory_on_hand)"
                        :hint="`${number(statistics.totals.inventory_reserved)} units reserved for confirmed orders`"
                    >
                        <SparklineChart
                            name="Units out"
                            :data="statistics.movement_series.series.outbound ?? []"
                        />
                    </StatCard>

                    <Card class="flex flex-col p-5">
                        <div
                            class="flex items-center justify-between border-b border-dashed border-border pb-3"
                        >
                            <h3 class="text-sm font-semibold">Catalogue</h3>
                            <Link
                                :href="products.index.url()"
                                class="text-xs text-muted-foreground transition-colors hover:text-foreground"
                                >See all</Link
                            >
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Products
                                </dt>
                                <dd class="text-xl font-bold">
                                    {{ number(statistics.totals.products) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Categories
                                </dt>
                                <dd class="text-xl font-bold">
                                    {{ number(statistics.totals.categories) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Suppliers
                                </dt>
                                <dd class="text-xl font-bold">
                                    {{ number(statistics.totals.active_suppliers) }}
                                    <span class="text-xs font-normal text-muted-foreground">
                                        / {{ number(statistics.totals.suppliers) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Low stock
                                </dt>
                                <dd class="flex items-center gap-1.5 text-xl font-bold">
                                    {{ number(statistics.totals.low_stock_products) }}
                                    <TriangleAlertIcon
                                        v-if="statistics.totals.low_stock_products"
                                        class="size-4 text-warning"
                                    />
                                </dd>
                            </div>
                        </dl>
                    </Card>
                </div>

                <div class="grid gap-6 xl:grid-cols-3">
                    <Card class="xl:col-span-2">
                        <CardHeader>
                            <template #title>
                                <CardTitle
                                    :description="`Revenue and order count per day, last ${statistics.revenue.window_days} days`"
                                    >Sales activity</CardTitle
                                >
                            </template>
                            <template #actions>
                                <Badge
                                    v-if="statistics.revenue.delta_percent !== null"
                                    :variant="
                                        statistics.revenue.delta_percent >= 0
                                            ? 'success'
                                            : 'danger'
                                    "
                                >
                                    <ArrowUpRightIcon
                                        v-if="statistics.revenue.delta_percent >= 0"
                                    />
                                    <ArrowDownRightIcon v-else />
                                    {{ statistics.revenue.delta_percent }}% vs
                                    previous period
                                </Badge>
                            </template>
                        </CardHeader>

                        <div class="px-2 pb-4 pt-2">
                            <LineChart
                                :series="salesSeries"
                                :categories="statistics.sales_series.labels"
                                dash-secondary
                            />
                        </div>
                    </Card>

                    <Card>
                        <CardHeader>
                            <template #title>
                                <CardTitle description="Every order, by state"
                                    >Orders</CardTitle
                                >
                            </template>
                        </CardHeader>

                        <div class="px-2 pb-4 pt-4">
                            <DonutChart
                                v-if="statusValues.length"
                                :labels="statusLabels"
                                :values="statusValues"
                                total-label="Orders"
                            />
                            <EmptyState
                                v-else
                                title="No orders yet"
                                description="Order states appear here once the store starts selling."
                            />
                        </div>
                    </Card>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <template #title>
                                <CardTitle
                                    :description="`Units received and issued per day, last ${statistics.revenue.window_days} days`"
                                    >Stock flow</CardTitle
                                >
                            </template>
                            <template #actions>
                                <Link
                                    :href="stock.index.url()"
                                    class="text-xs text-muted-foreground transition-colors hover:text-foreground"
                                    >All stock</Link
                                >
                            </template>
                        </CardHeader>

                        <div class="px-2 pb-4 pt-2">
                            <BarChart
                                :series="movementSeries"
                                :categories="statistics.movement_series.labels"
                            />
                        </div>
                    </Card>

                    <Card>
                        <CardHeader>
                            <template #title>
                                <CardTitle
                                    description="Units at or below their target level"
                                    >Low stock</CardTitle
                                >
                            </template>
                            <template #actions>
                                <Link
                                    :href="stock.planner.url()"
                                    class="text-xs text-muted-foreground transition-colors hover:text-foreground"
                                    >Plan reorders</Link
                                >
                            </template>
                        </CardHeader>

                        <ul
                            v-if="statistics.low_stock_items.length"
                            class="divide-y divide-border"
                        >
                            <li
                                v-for="item in statistics.low_stock_items"
                                :key="item.id"
                                class="flex items-center justify-between gap-3 px-5 py-3"
                            >
                                <div class="min-w-0">
                                    <Link
                                        :href="products.show.url(item.product_id)"
                                        class="block truncate text-[0.8125rem] font-medium hover:underline"
                                    >
                                        {{ item.product.name }}
                                    </Link>
                                    <span
                                        class="font-mono text-[11px] text-muted-foreground"
                                    >
                                        {{ item.variant?.sku ?? item.product.sku ?? '—' }}
                                    </span>
                                </div>

                                <Badge
                                    :variant="
                                        item.quantity_on_hand <= 0
                                            ? 'danger'
                                            : 'warning'
                                    "
                                >
                                    {{ item.quantity_on_hand }} /
                                    {{ item.product.low_stock_threshold }}
                                </Badge>
                            </li>
                        </ul>

                        <EmptyState
                            v-else
                            title="Everything is stocked"
                            description="No unit is at or below its target level."
                        >
                            <template #icon><BoxesIcon class="size-5" /></template>
                        </EmptyState>
                    </Card>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <template #title>
                                <CardTitle>Recent orders</CardTitle>
                            </template>
                            <template #actions>
                                <Link
                                    :href="orders.index.url()"
                                    class="text-xs text-muted-foreground transition-colors hover:text-foreground"
                                    >See all</Link
                                >
                            </template>
                        </CardHeader>

                        <ul
                            v-if="statistics.recent_orders.length"
                            class="divide-y divide-border"
                        >
                            <li
                                v-for="order in statistics.recent_orders"
                                :key="order.id"
                                class="flex items-center justify-between gap-3 px-5 py-3"
                            >
                                <div class="min-w-0">
                                    <Link
                                        :href="orders.show.url(order.id)"
                                        class="block truncate font-mono text-[0.8125rem] font-medium hover:underline"
                                    >
                                        {{ order.order_number }}
                                    </Link>
                                    <span class="text-[11px] text-muted-foreground">
                                        {{ order.customer_name }} ·
                                        {{ date(order.created_at) }}
                                    </span>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="text-[0.8125rem] font-medium">
                                        {{ money(order.total, order.currency) }}
                                    </span>
                                    <StatusBadge :status="order.status" size="sm" />
                                </div>
                            </li>
                        </ul>

                        <EmptyState v-else title="No orders yet" />
                    </Card>

                    <Card>
                        <CardHeader>
                            <template #title>
                                <CardTitle
                                    description="Newest entries in the stock ledger"
                                    >Recent movements</CardTitle
                                >
                            </template>
                        </CardHeader>

                        <ul
                            v-if="statistics.recent_movements.length"
                            class="divide-y divide-border"
                        >
                            <li
                                v-for="movement in statistics.recent_movements"
                                :key="movement.id"
                                class="flex items-center justify-between gap-3 px-5 py-3"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-[0.8125rem] font-medium">
                                        {{ movement.product?.name ?? '—' }}
                                    </p>
                                    <span class="text-[11px] text-muted-foreground">
                                        {{ humanize(movement.type) }} ·
                                        {{ date(movement.created_at) }}
                                    </span>
                                </div>

                                <Badge
                                    :variant="
                                        movement.quantity >= 0 ? 'success' : 'danger'
                                    "
                                >
                                    {{ movement.quantity > 0 ? '+' : '' }}{{
                                        movement.quantity
                                    }}
                                </Badge>
                            </li>
                        </ul>

                        <EmptyState v-else title="No stock movements yet">
                            <template #icon><PackageIcon class="size-5" /></template>
                        </EmptyState>
                    </Card>
                </div>
            </div>
        </Deferred>
    </AppLayout>
</template>
