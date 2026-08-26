<?php

namespace Modules\Inventory\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Inventory\Models\Expense;
use Modules\Inventory\Models\Order;

/**
 * Daily trading report: what was sold, what those goods cost, what was spent
 * running the store, and what is left.
 *
 *   sales        revenue on orders placed that day, cancelled ones excluded
 *   cogs         the cost snapshot on those orders' lines
 *   gross profit sales - cogs
 *   expenses     operating expenses recorded against that day
 *   net profit   gross profit - expenses
 *
 * Stock purchases are never counted as an expense: what inventory costs
 * arrives as cost of goods sold on the order that sold it. Counting the
 * purchase as well would subtract the same money twice.
 *
 * Nothing here is stored. Every figure is aggregated from orders, order lines
 * and expenses on read, so a report can never disagree with the records it
 * describes.
 */
class ReportService
{
    /**
     * Days covered when the caller names no range.
     */
    private const DEFAULT_RANGE_DAYS = 30;

    /**
     * Hard cap on the range, so one request cannot ask for a decade of rows.
     */
    private const MAX_RANGE_DAYS = 366;

    /**
     * @param  array{from?: string|null, to?: string|null, customer?: string|null, customer_id?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function daily(array $filters): array
    {
        [$from, $to] = $this->range($filters);

        $sales = $this->salesByDay($from, $to, $filters);
        $cogs = $this->costByDay($from, $to, $filters);

        // Expenses belong to the store, not to a customer. Attributing them to
        // one customer's sales would invent a net profit that means nothing,
        // so they are withheld while a customer filter is applied.
        $attributable = ! $this->hasCustomerFilter($filters);
        $expenses = $attributable ? $this->expensesByDay($from, $to) : [];

        $days = [];

        foreach ($this->eachDay($from, $to) as $day) {
            $daySales = round((float) ($sales[$day]['total'] ?? 0), 2);
            $dayCogs = round((float) ($cogs[$day]['cost'] ?? 0), 2);
            $dayExpenses = round((float) ($expenses[$day] ?? 0), 2);
            $grossProfit = round($daySales - $dayCogs, 2);

            $days[] = [
                'date' => $day,
                'orders_count' => (int) ($sales[$day]['orders'] ?? 0),
                'sales' => $daySales,
                'cogs' => $dayCogs,
                'gross_profit' => $grossProfit,
                'expenses' => $attributable ? $dayExpenses : null,
                'net_profit' => $attributable
                    ? round($grossProfit - $dayExpenses, 2)
                    : null,
                // Lines with no cost snapshot make the day's margin a
                // best case rather than a fact.
                'lines_without_cost' => (int) ($cogs[$day]['unknown'] ?? 0),
            ];
        }

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'days' => $days,
            'totals' => $this->totals($days, $attributable),
            'meta' => [
                'expenses_attributable' => $attributable,
                'customer_filter' => $filters['customer'] ?? null,
                'lines_without_cost' => array_sum(array_column($days, 'lines_without_cost')),
            ],
        ];
    }

    /**
     * Expense breakdown by category for the same range, which is what makes a
     * net profit figure explainable rather than just smaller.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, float>
     */
    public function expensesByCategory(array $filters): array
    {
        if ($this->hasCustomerFilter($filters)) {
            return [];
        }

        [$from, $to] = $this->range($filters);

        return Expense::query()
            ->between($from->toDateString(), $to->toDateString())
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->pluck('total', 'category')
            ->map(fn ($total): float => round((float) $total, 2))
            ->all();
    }

    /**
     * Revenue and order count per day.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, array{total: float, orders: int}>
     */
    private function salesByDay(Carbon $from, Carbon $to, array $filters): array
    {
        return $this->ordersInRange($from, $to, $filters)
            ->selectRaw('date(orders.created_at) as day, sum(orders.total) as total, count(*) as orders')
            ->groupBy('day')
            ->get()
            ->keyBy('day')
            ->map(fn ($row): array => [
                'total' => (float) $row->total,
                'orders' => (int) $row->orders,
            ])
            ->all();
    }

    /**
     * Cost of the goods on those orders, from the snapshot taken at intake,
     * plus a count of lines that carry no cost at all.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, array{cost: float, unknown: int}>
     */
    private function costByDay(Carbon $from, Carbon $to, array $filters): array
    {
        return $this->ordersInRange($from, $to, $filters)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw(
                'date(orders.created_at) as day,'
                .' sum(coalesce(order_items.unit_cost, 0) * order_items.quantity) as cost,'
                .' sum(case when order_items.unit_cost is null then 1 else 0 end) as unknown'
            )
            ->groupBy('day')
            ->get()
            ->keyBy('day')
            ->map(fn ($row): array => [
                'cost' => (float) $row->cost,
                'unknown' => (int) $row->unknown,
            ])
            ->all();
    }

    /**
     * @return array<string, float>
     */
    private function expensesByDay(Carbon $from, Carbon $to): array
    {
        return Expense::query()
            ->between($from->toDateString(), $to->toDateString())
            ->selectRaw('spent_on as day, sum(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->mapWithKeys(fn ($total, $day): array => [
                Carbon::parse($day)->toDateString() => (float) $total,
            ])
            ->all();
    }

    /**
     * Base query for every sales figure: orders placed in the range that still
     * count, narrowed to a customer when one was named.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Order>
     */
    private function ordersInRange(Carbon $from, Carbon $to, array $filters): Builder
    {
        return Order::query()
            ->billable()
            ->whereBetween('orders.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when(
                $filters['customer_id'] ?? null,
                fn (Builder $query, int|string $id) => $query->where('orders.customer_id', $id),
            )
            ->when(
                $filters['customer'] ?? null,
                // Matches the name on the order and the linked record alike, so
                // a walk-in sale typed as "Emma Chen" is found next to the same
                // person's account.
                fn (Builder $query, string $name) => $query->where(
                    fn (Builder $inner) => $inner
                        ->where('orders.customer_name', 'like', "%{$name}%")
                        ->orWhere('orders.customer_email', 'like', "%{$name}%")
                        ->orWhereHas('customer', fn (Builder $customer) => $customer
                            ->where('name', 'like', "%{$name}%")
                            ->orWhere('code', 'like', "%{$name}%")),
                ),
            );
    }

    /**
     * @param  array<int, array<string, mixed>>  $days
     * @return array<string, mixed>
     */
    private function totals(array $days, bool $attributable): array
    {
        $sales = round(array_sum(array_column($days, 'sales')), 2);
        $cogs = round(array_sum(array_column($days, 'cogs')), 2);
        $expenses = round(array_sum(array_map(
            fn (array $day): float => (float) ($day['expenses'] ?? 0),
            $days,
        )), 2);
        $grossProfit = round($sales - $cogs, 2);

        return [
            'orders_count' => array_sum(array_column($days, 'orders_count')),
            'sales' => $sales,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'expenses' => $attributable ? $expenses : null,
            'net_profit' => $attributable ? round($grossProfit - $expenses, 2) : null,
            // Margin on sales, not on cost. Null rather than 0% when nothing
            // sold: "no margin" and "nothing to measure" are different.
            'gross_margin_percent' => $sales > 0
                ? round(($grossProfit / $sales) * 100, 1)
                : null,
            'net_margin_percent' => $attributable && $sales > 0
                ? round((($grossProfit - $expenses) / $sales) * 100, 1)
                : null,
        ];
    }

    /**
     * The requested range, defaulted and clamped.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(array $filters): array
    {
        $to = isset($filters['to']) && $filters['to']
            ? Carbon::parse($filters['to'])->startOfDay()
            : Carbon::now()->startOfDay();

        $from = isset($filters['from']) && $filters['from']
            ? Carbon::parse($filters['from'])->startOfDay()
            : $to->copy()->subDays(self::DEFAULT_RANGE_DAYS - 1);

        // A backwards range is a typo, not a request for no rows.
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) >= self::MAX_RANGE_DAYS) {
            $from = $to->copy()->subDays(self::MAX_RANGE_DAYS - 1);
        }

        return [$from, $to];
    }

    /**
     * Every date in the range, so a day with no trading reports as a zero row
     * rather than being missing from the table.
     *
     * @return array<int, string>
     */
    private function eachDay(Carbon $from, Carbon $to): array
    {
        $days = [];

        for ($day = $from->copy(); $day->lessThanOrEqualTo($to); $day->addDay()) {
            $days[] = $day->toDateString();
        }

        return $days;
    }

    private function hasCustomerFilter(array $filters): bool
    {
        return filled($filters['customer'] ?? null)
            || filled($filters['customer_id'] ?? null);
    }
}
