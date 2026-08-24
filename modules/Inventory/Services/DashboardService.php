<?php

namespace Modules\Inventory\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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
