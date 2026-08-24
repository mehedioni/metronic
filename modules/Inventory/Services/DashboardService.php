<?php

namespace Modules\Inventory\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\OrderStatus;
use Modules\Inventory\Enums\RecordStatus;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\InboundReceipt;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Supplier;

/**
 * Aggregates for the dashboard. Every figure is a database aggregate — no
 * collection is ever hydrated just to be counted.
 */
class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function statistics(): array
    {
        $recentLimit = (int) config('inventory.dashboard.recent_limit', 10);

        return [
            'totals' => $this->totals(),
            'revenue' => $this->revenue(),
            'sales_series' => $this->salesSeries(),
            'movement_series' => $this->movementSeries(),
            'orders_by_status' => $this->ordersByStatus(),
            'movement_summary' => $this->movementSummary(),
            'low_stock_items' => $this->lowStockItems(),
            'recent_movements' => $this->recentMovements($recentLimit),
            'recent_receipts' => $this->recentReceipts($recentLimit),
            'recent_orders' => $this->recentOrders($recentLimit),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function totals(): array
    {
        return [
            'products' => Product::query()->count(),
            'categories' => Category::query()->count(),
            'suppliers' => Supplier::query()->count(),
            'active_suppliers' => Supplier::query()->where('status', RecordStatus::Active)->count(),
            'inventory_on_hand' => (int) InventoryItem::query()->sum('quantity_on_hand'),
            'inventory_reserved' => (int) InventoryItem::query()->sum('quantity_reserved'),
            'low_stock_products' => Product::query()->lowStock()->count(),
            'orders' => Order::query()->count(),
        ];
    }

    /**
     * Revenue for the trend window and the window before it, so the dashboard
     * can show a period-over-period delta. Cancelled orders never count.
     *
     * @return array<string, float|null>
     */
    private function revenue(): array
    {
        $days = $this->trendDays();
        $now = Carbon::now();

        $current = $this->revenueBetween($now->copy()->subDays($days), $now);
        $previous = $this->revenueBetween(
            $now->copy()->subDays($days * 2),
            $now->copy()->subDays($days),
        );

        return [
            'window_days' => $days,
            'current' => $current,
            'previous' => $previous,
            // Null rather than 0% when there is no baseline: "no change" and
            // "nothing to compare against" are different statements.
            'delta_percent' => $previous > 0
                ? round((($current - $previous) / $previous) * 100, 1)
                : null,
        ];
    }

    private function revenueBetween(Carbon $from, Carbon $to): float
    {
        return (float) Order::query()
            ->whereNot('status', OrderStatus::Cancelled)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total');
    }

    /**
     * Orders and revenue per day across the trend window, with every day in
     * the range present — a gap in the data must render as a zero, not as a
     * missing point that the chart would interpolate over.
     *
     * @return array<string, mixed>
     */
    private function salesSeries(): array
    {
        $days = $this->trendDays();
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = Order::query()
            ->selectRaw('date(created_at) as day, count(*) as orders, sum(total) as revenue')
            ->whereNot('status', OrderStatus::Cancelled)
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('revenue', 'day');

        $counts = Order::query()
            ->selectRaw('date(created_at) as day, count(*) as orders')
            ->whereNot('status', OrderStatus::Cancelled)
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('orders', 'day');

        return $this->fillDays(
            $start,
            $days,
            fn (string $day): array => [
                'revenue' => round((float) ($rows[$day] ?? 0), 2),
                'orders' => (int) ($counts[$day] ?? 0),
            ],
        );
    }

    /**
     * Units in and out per day across the trend window.
     *
     * @return array<string, mixed>
     */
    private function movementSeries(): array
    {
        $days = $this->trendDays();
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        $inbound = StockMovement::query()
            ->selectRaw('date(created_at) as day, sum(quantity) as units')
            ->inbound()
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('units', 'day');

        $outbound = StockMovement::query()
            ->selectRaw('date(created_at) as day, sum(quantity) as units')
            ->outbound()
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('units', 'day');

        return $this->fillDays(
            $start,
            $days,
            fn (string $day): array => [
                'inbound' => (int) ($inbound[$day] ?? 0),
                'outbound' => abs((int) ($outbound[$day] ?? 0)),
            ],
        );
    }

    /**
     * Turn a per-day lookup into {labels, series} with no missing days.
     *
     * @param  callable(string): array<string, float|int>  $valuesFor
     * @return array<string, mixed>
     */
    private function fillDays(Carbon $start, int $days, callable $valuesFor): array
    {
        $labels = [];
        $series = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->copy()->addDays($offset);
            $key = $date->toDateString();

            $labels[] = $date->format('d M');

            foreach ($valuesFor($key) as $name => $value) {
                $series[$name][] = $value;
            }
        }

        return ['labels' => $labels, 'series' => $series];
    }

    private function trendDays(): int
    {
        return (int) config('inventory.dashboard.trend_days', 14);
    }

    /**
     * @return array<string, int>
     */
    private function ordersByStatus(): array
    {
        return Order::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * Inbound/outbound unit totals plus a per-type breakdown.
     *
     * @return array<string, mixed>
     */
    private function movementSummary(): array
    {
        $byType = StockMovement::query()
            ->select('type', DB::raw('sum(quantity) as aggregate'))
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->map(fn ($sum): int => (int) $sum)
            ->all();

        return [
            'inbound_units' => (int) StockMovement::query()->inbound()->sum('quantity'),
            'outbound_units' => abs((int) StockMovement::query()->outbound()->sum('quantity')),
            'by_type' => $byType,
        ];
    }

    /**
     * @return Collection<int, InventoryItem>
     */
    private function lowStockItems(): Collection
    {
        return InventoryItem::query()
            ->with(['product:id,name,sku,low_stock_threshold', 'variant:id,sku,name'])
            ->lowStock()
            ->orderBy('quantity_on_hand')
            ->limit((int) config('inventory.dashboard.low_stock_limit', 10))
            ->get();
    }

    /**
     * @return Collection<int, StockMovement>
     */
    private function recentMovements(int $limit): Collection
    {
        return StockMovement::query()
            ->with(['product:id,name,sku', 'variant:id,sku', 'user:id,name'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, InboundReceipt>
     */
    private function recentReceipts(int $limit): Collection
    {
        return InboundReceipt::query()
            ->with('supplier:id,company_name')
            ->withSum('items', 'quantity')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Order>
     */
    private function recentOrders(int $limit): Collection
    {
        return Order::query()
            ->withCount('items')
            ->latest()
            ->limit($limit)
            ->get();
    }
}
