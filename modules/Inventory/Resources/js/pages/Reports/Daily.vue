<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import { DownloadIcon, InfoIcon, TriangleAlertIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useCsvExport } from '@/composables/useCsvExport';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, money, number, percent } from '@/lib/format';
import { humanize } from '@/lib/status';
import reports from '@/routes/inventory/reports';

interface Day {
    date: string;
    orders_count: number;
    sales: number;
    cogs: number;
    gross_profit: number;
    expenses: number | null;
    net_profit: number | null;
    lines_without_cost: number;
}

interface Report {
    range: { from: string; to: string };
    days: Day[];
    totals: {
        orders_count: number;
        sales: number;
        cogs: number;
        gross_profit: number;
        expenses: number | null;
        net_profit: number | null;
        gross_margin_percent: number | null;
        net_margin_percent: number | null;
    };
    meta: {
        expenses_attributable: boolean;
        customer_filter: string | null;
        lines_without_cost: number;
        currencies: string[];
    };
}

const props = defineProps<{
    report: Report;
    filters: Record<string, unknown>;
    expensesByCategory?: Record<string, number>;
}>();

const { params, loading, reset } = useTableQuery({
    url: reports.daily.url(),
    filters: props.filters,
    only: ['report', 'filters', 'expensesByCategory'],
});

const { exportRows } = useCsvExport();

const days = computed(() => props.report.days);
const totals = computed(() => props.report.totals);
const meta = computed(() => props.report.meta);

/** One currency is assumed; the sums stop meaning anything otherwise. */
const mixedCurrency = computed(() => meta.value.currencies.length > 1);
const currency = computed(() => meta.value.currencies[0] ?? 'USD');

const labels = computed(() =>
    days.value.map((day) =>
        new Intl.DateTimeFormat(undefined, {
            day: '2-digit',
            month: 'short',
        }).format(new Date(day.date)),
    ),
);

const tradeSeries = computed(() => {
    const series = [
        { name: 'Sales', data: days.value.map((day) => day.sales) },
        { name: 'Cost of goods', data: days.value.map((day) => day.cogs) },
    ];

    if (meta.value.expenses_attributable) {
        series.push({
            name: 'Expenses',
            data: days.value.map((day) => day.expenses ?? 0),
        });
    }

    return series;
});

const profitSeries = computed(() => {
    const series = [
        { name: 'Gross profit', data: days.value.map((day) => day.gross_profit) },
    ];

    if (meta.value.expenses_attributable) {
        series.push({
            name: 'Net profit',
            data: days.value.map((day) => day.net_profit ?? 0),
        });
    }

    return series;
});

const categoryLabels = computed(() =>
    Object.keys(props.expensesByCategory ?? {}).map((key) => humanize(key)),
);

const categoryValues = computed(() =>
    Object.values(props.expensesByCategory ?? {}),
);

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Reports' },
    { label: 'Daily sales & profit' },
];

/** Quick ranges, because typing two dates for "this month" is a chore. */
function applyRange(days: number) {
    const to = new Date();
    const from = new Date();

    from.setDate(from.getDate() - (days - 1));

    params.from = from.toISOString().slice(0, 10);
    params.to = to.toISOString().slice(0, 10);
}

function exportReport() {
    exportRows(`daily-report-${meta.value.customer_filter ?? 'all'}`, days.value, [
        { label: 'Date', value: (row) => row.date },
        { label: 'Orders', value: (row) => row.orders_count },
        { label: 'Sales', value: (row) => row.sales },
        { label: 'Cost of goods', value: (row) => row.cogs },
        { label: 'Gross profit', value: (row) => row.gross_profit },
        { label: 'Expenses', value: (row) => row.expenses ?? '' },
        { label: 'Net profit', value: (row) => row.net_profit ?? '' },
        { label: 'Lines without cost', value: (row) => row.lines_without_cost },
    ]);
}

/** Profit reads in the danger colour when the day lost money. */
function toneFor(value: number | null): string {
    if (value === null) {
        return 'text-muted-foreground';
    }

    if (value < 0) {
        return 'text-danger';
    }

    return value > 0 ? 'text-success' : 'text-muted-foreground';
}
</script>

<template>
    <Head title="Daily sales & profit" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="Daily sales & profit"
            :description="`${date(report.range.from)} – ${date(report.range.to)}. Sales count orders placed that day, cancelled ones excluded.`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Button variant="outline" size="dense" @click="exportReport">
                    <DownloadIcon />
                    Export
                </Button>
            </template>
        </PageHeader>

        <Card>
            <div class="flex flex-wrap items-end justify-between gap-3 px-5 py-3.5">
                <div class="flex flex-wrap items-end gap-3">
                    <label class="flex flex-col gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground"
                            >From</span
                        >
                        <Input v-model="params.from" type="date" class="w-40" />
                    </label>

                    <label class="flex flex-col gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground"
                            >To</span
                        >
                        <Input v-model="params.to" type="date" class="w-40" />
                    </label>

                    <label class="flex flex-col gap-1.5">
                        <span class="text-[11px] font-medium text-muted-foreground"
                            >Customer name</span
                        >
                        <Input
                            v-model="params.customer"
                            placeholder="Any customer"
                            class="w-64"
                        />
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="ghost" size="dense" @click="applyRange(7)">
                        7 days
                    </Button>
                    <Button variant="ghost" size="dense" @click="applyRange(30)">
                        30 days
                    </Button>
                    <Button variant="ghost" size="dense" @click="applyRange(90)">
                        90 days
                    </Button>
                    <Button variant="outline" size="dense" @click="reset">
                        Clear
                    </Button>
                </div>
            </div>
        </Card>

        <div
            v-if="!meta.expenses_attributable"
            class="flex items-start gap-2.5 rounded-lg border border-info/20 bg-info-soft px-4 py-3 text-[0.8125rem] text-info"
        >
            <InfoIcon class="mt-px size-4 shrink-0" />
            <p>
                Filtered to one customer, so only sales, cost of goods and gross
                profit are shown. Operating expenses belong to the store rather
                than to a customer — splitting them across one buyer's orders
                would invent a net profit that means nothing.
            </p>
        </div>

        <div
            v-if="meta.lines_without_cost"
            class="flex items-start gap-2.5 rounded-lg border border-warning/20 bg-warning-soft px-4 py-3 text-[0.8125rem] text-warning"
        >
            <TriangleAlertIcon class="mt-px size-4 shrink-0" />
            <p>
                {{ number(meta.lines_without_cost) }} order
                {{ meta.lines_without_cost === 1 ? 'line' : 'lines' }} in this
                range carry no cost price, so they contribute nothing to cost of
                goods. Profit here is therefore a best case — set a cost price
                on those products to make it exact.
            </p>
        </div>

        <div
            v-if="mixedCurrency"
            class="flex items-start gap-2.5 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-[0.8125rem] text-danger"
        >
            <TriangleAlertIcon class="mt-px size-4 shrink-0" />
            <p>
                Orders in this range use more than one currency
                ({{ meta.currencies.join(', ') }}). These totals are plain sums
                and do not convert between them.
            </p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-5">
            <Card class="p-5">
                <p class="text-xs text-muted-foreground">Sales</p>
                <p class="mt-1 text-2xl font-bold tracking-tight">
                    {{ money(totals.sales, currency) }}
                </p>
                <p class="mt-0.5 text-[11px] text-muted-foreground">
                    {{ number(totals.orders_count) }} orders
                </p>
            </Card>

            <Card class="p-5">
                <p class="text-xs text-muted-foreground">Cost of goods</p>
                <p class="mt-1 text-2xl font-bold tracking-tight">
                    {{ money(totals.cogs, currency) }}
                </p>
                <p class="mt-0.5 text-[11px] text-muted-foreground">
                    What the goods sold cost the store
                </p>
            </Card>

            <Card class="p-5">
                <p class="text-xs text-muted-foreground">Gross profit</p>
                <p
                    class="mt-1 text-2xl font-bold tracking-tight"
                    :class="toneFor(totals.gross_profit)"
                >
                    {{ money(totals.gross_profit, currency) }}
                </p>
                <p class="mt-0.5 text-[11px] text-muted-foreground">
                    {{
                        totals.gross_margin_percent === null
                            ? 'No sales to measure'
                            : `${percent(totals.gross_margin_percent)} margin`
                    }}
                </p>
            </Card>

            <Card class="p-5">
                <p class="text-xs text-muted-foreground">Expenses</p>
                <p class="mt-1 text-2xl font-bold tracking-tight">
                    {{
                        totals.expenses === null
                            ? '—'
                            : money(totals.expenses, currency)
                    }}
                </p>
                <p class="mt-0.5 text-[11px] text-muted-foreground">
                    Rent, wages, utilities — not stock
                </p>
            </Card>

            <Card class="p-5">
                <p class="text-xs text-muted-foreground">Net profit</p>
                <p
                    class="mt-1 text-2xl font-bold tracking-tight"
                    :class="toneFor(totals.net_profit)"
                >
                    {{
                        totals.net_profit === null
                            ? '—'
                            : money(totals.net_profit, currency)
                    }}
                </p>
                <p class="mt-0.5 text-[11px] text-muted-foreground">
                    {{
                        totals.net_margin_percent === null
                            ? 'Not attributable per customer'
                            : `${percent(totals.net_margin_percent)} margin`
                    }}
                </p>
            </Card>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <Card class="xl:col-span-2">
                <CardHeader>
                    <template #title>
                        <CardTitle description="Sales against what it cost to make them"
                            >Trading</CardTitle
                        >
                    </template>
                </CardHeader>

                <div class="px-2 pb-4 pt-2">
                    <BarChart :series="tradeSeries" :categories="labels" />
                </div>
            </Card>

            <Card>
                <CardHeader>
                    <template #title>
                        <CardTitle description="Where the expense money went"
                            >Expenses by category</CardTitle
                        >
                    </template>
                </CardHeader>

                <Deferred data="expensesByCategory">
                    <template #fallback>
                        <div class="m-5 h-48 animate-pulse rounded-md bg-muted/40" />
                    </template>

                    <div class="px-2 pb-4 pt-4">
                        <DonutChart
                            v-if="categoryValues.length"
                            :labels="categoryLabels"
                            :values="categoryValues"
                            total-label="Spent"
                        />
                        <EmptyState
                            v-else
                            title="Nothing recorded"
                            :description="
                                meta.expenses_attributable
                                    ? 'No expenses fall in this range.'
                                    : 'Expenses are not shown while filtering by customer.'
                            "
                        />
                    </div>
                </Deferred>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle description="Gross profit is before expenses; net is after"
                        >Profit</CardTitle
                    >
                </template>
            </CardHeader>

            <div class="px-2 pb-4 pt-2">
                <LineChart :series="profitSeries" :categories="labels" />
            </div>
        </Card>

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle
                        :description="`${days.length} days, including days with no trading`"
                        >Day by day</CardTitle
                    >
                </template>
                <template #actions>
                    <Badge v-if="loading" variant="outline">Updating…</Badge>
                </template>
            </CardHeader>

            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead
                        class="border-b border-border bg-muted/70 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground"
                    >
                        <tr>
                            <th class="px-5 py-3 text-start">Date</th>
                            <th class="px-5 py-3 text-center">Orders</th>
                            <th class="px-5 py-3 text-end">Sales</th>
                            <th class="px-5 py-3 text-end">Cost of goods</th>
                            <th class="px-5 py-3 text-end">Gross profit</th>
                            <th class="px-5 py-3 text-end">Expenses</th>
                            <th class="px-5 py-3 text-end">Net profit</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="day in days"
                            :key="day.date"
                            class="transition-colors hover:bg-muted/60"
                            :class="day.orders_count ? '' : 'text-muted-foreground'"
                        >
                            <td class="px-5 py-2.5">
                                {{ date(day.date) }}
                                <span
                                    v-if="day.lines_without_cost"
                                    class="ms-1 text-[11px] text-warning"
                                    :title="`${day.lines_without_cost} line(s) have no cost price`"
                                    >·cost?</span
                                >
                            </td>
                            <td class="px-5 py-2.5 text-center">
                                {{ day.orders_count || '—' }}
                            </td>
                            <td class="px-5 py-2.5 text-end">
                                {{ day.sales ? money(day.sales, currency) : '—' }}
                            </td>
                            <td class="px-5 py-2.5 text-end">
                                {{ day.cogs ? money(day.cogs, currency) : '—' }}
                            </td>
                            <td
                                class="px-5 py-2.5 text-end font-medium"
                                :class="day.sales ? toneFor(day.gross_profit) : ''"
                            >
                                {{
                                    day.sales
                                        ? money(day.gross_profit, currency)
                                        : '—'
                                }}
                            </td>
                            <td class="px-5 py-2.5 text-end">
                                {{
                                    day.expenses === null
                                        ? '—'
                                        : day.expenses
                                          ? money(day.expenses, currency)
                                          : '—'
                                }}
                            </td>
                            <td
                                class="px-5 py-2.5 text-end font-medium"
                                :class="
                                    day.net_profit === null
                                        ? ''
                                        : toneFor(day.net_profit)
                                "
                            >
                                {{
                                    day.net_profit === null
                                        ? '—'
                                        : money(day.net_profit, currency)
                                }}
                            </td>
                        </tr>
                    </tbody>

                    <tfoot
                        class="border-t border-border bg-muted/40 text-[0.8125rem] font-semibold"
                    >
                        <tr>
                            <td class="px-5 py-3">Total</td>
                            <td class="px-5 py-3 text-center">
                                {{ number(totals.orders_count) }}
                            </td>
                            <td class="px-5 py-3 text-end">
                                {{ money(totals.sales, currency) }}
                            </td>
                            <td class="px-5 py-3 text-end">
                                {{ money(totals.cogs, currency) }}
                            </td>
                            <td
                                class="px-5 py-3 text-end"
                                :class="toneFor(totals.gross_profit)"
                            >
                                {{ money(totals.gross_profit, currency) }}
                            </td>
                            <td class="px-5 py-3 text-end">
                                {{
                                    totals.expenses === null
                                        ? '—'
                                        : money(totals.expenses, currency)
                                }}
                            </td>
                            <td
                                class="px-5 py-3 text-end"
                                :class="toneFor(totals.net_profit)"
                            >
                                {{
                                    totals.net_profit === null
                                        ? '—'
                                        : money(totals.net_profit, currency)
                                }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </Card>
    </AppLayout>
</template>
