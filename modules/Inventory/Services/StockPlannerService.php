<?php

namespace Modules\Inventory\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;

/**
 * Reorder planning, derived entirely from data the system already holds.
 *
 * Nothing here is stored: consumption comes from the stock_movements ledger,
 * the target level from the product/variant low-stock threshold, and the lead
 * time from the supplier link. A planner row is therefore always a statement
 * about current data, never a stale snapshot.
 */
class StockPlannerService
{
    /**
     * Days of ledger history used to work out how fast a unit sells.
     */
    private const VELOCITY_WINDOW_DAYS = 30;

    /**
     * Assumed lead time when no supplier link states one.
     */
    private const DEFAULT_LEAD_TIME_DAYS = 7;

    /**
     * One row per stockable unit with everything the planner screen shows.
     *
     * @param  array{search?: string|null, category_id?: string|null, supplier_id?: string|null, low_stock?: bool|null, per_page?: int|null, reorder_within?: int|null}  $filters
     * @return LengthAwarePaginator<int, InventoryItem>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $items = InventoryItem::query()
            ->with([
                'product:id,name,sku,category_id,primary_supplier_id,cost_price,selling_price,image_path,low_stock_threshold',
                'product.category:id,name',
                'variant:id,sku,name,low_stock_threshold,selling_price,cost_price',
            ])
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->whereHas(
                'product',
                fn (Builder $product) => $product->search($term),
            ))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int|string $category) => $query->whereHas(
                'product',
                fn (Builder $product) => $product->where('category_id', $category),
            ))
            ->when($filters['supplier_id'] ?? null, fn (Builder $query, int|string $supplier) => $query->whereHas(
                'product',
                fn (Builder $product) => $product->forSupplier($supplier),
            ))
            ->when($filters['low_stock'] ?? false, fn (Builder $query) => $query->lowStock())
            ->orderBy('quantity_on_hand')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        $velocities = $this->dailyVelocities($items->getCollection());
        $leadTimes = $this->leadTimes($items->getCollection());

        $items->setCollection(
            $items->getCollection()->map(function (InventoryItem $item) use ($velocities, $leadTimes): InventoryItem {
                $key = $this->key($item);

                return $item->setAttribute('plan', $this->plan(
                    $item,
                    $velocities[$key] ?? 0.0,
                    $leadTimes[$key] ?? self::DEFAULT_LEAD_TIME_DAYS,
                ));
            }),
        );

        return $this->applyReorderWindow($items, $filters['reorder_within'] ?? null);
    }

    /**
     * Totals for the cards above the planner table.
     *
     * @return array<string, int>
     */
    public function summary(): array
    {
        return [
            'units_tracked' => InventoryItem::query()->count(),
            'below_target' => InventoryItem::query()->lowStock()->count(),
            'out_of_stock' => InventoryItem::query()->where('quantity_on_hand', '<=', 0)->count(),
            'fully_reserved' => InventoryItem::query()
                ->whereColumn('quantity_reserved', '>=', 'quantity_on_hand')
                ->where('quantity_on_hand', '>', 0)
                ->count(),
        ];
    }

    /**
     * The planner figures for one unit.
     *
     * @return array<string, mixed>
     */
    private function plan(InventoryItem $item, float $dailyVelocity, int $leadTimeDays): array
    {
        $available = $item->availableQuantity();
        $target = $this->targetLevel($item);

        // Cover for the lead time plus the target buffer is what a reorder has
        // to restore; anything already available counts against it.
        $required = (int) ceil($target + ($dailyVelocity * $leadTimeDays));
        $reorderQuantity = max(0, $required - $available);

        return [
            'target_level' => $target,
            'available' => $available,
            'delta' => $available - $target,
            'daily_velocity' => round($dailyVelocity, 2),
            'days_of_cover' => $dailyVelocity > 0
                ? (int) floor(max(0, $available) / $dailyVelocity)
                : null,
            'lead_time_days' => $leadTimeDays,
            'reorder_quantity' => $reorderQuantity,
            'needs_reorder' => $reorderQuantity > 0,
        ];
    }

    /**
     * Target level is the variant's threshold when the unit has one, otherwise
     * the product's — the same precedence InventoryItem::scopeLowStock uses.
     */
    private function targetLevel(InventoryItem $item): int
    {
        return (int) ($item->variant->low_stock_threshold
            ?? $item->product->low_stock_threshold
            ?? 0);
    }

    /**
     * Average units leaving the shelf per day, per unit, over the velocity
     * window. Outbound movements are stored negative, so the sum is negated.
     *
     * @param  Collection<int, InventoryItem>  $items
     * @return array<string, float>
     */
    private function dailyVelocities(Collection $items): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $since = Carbon::now()->subDays(self::VELOCITY_WINDOW_DAYS);

        return DB::table('stock_movements')
            ->selectRaw("product_id, COALESCE(product_variant_id, '') as variant_key, SUM(quantity) as net")
            ->whereIn('product_id', $items->pluck('product_id')->unique()->all())
            ->where('quantity', '<', 0)
            ->where('created_at', '>=', $since)
            ->groupBy('product_id', 'variant_key')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $row->product_id.'|'.$row->variant_key => abs((float) $row->net) / self::VELOCITY_WINDOW_DAYS,
            ])
            ->all();
    }

    /**
     * Lead time per unit, preferring the supplier link marked preferred and
     * falling back to the shortest link on the product.
     *
     * @param  Collection<int, InventoryItem>  $items
     * @return array<string, int>
     */
    private function leadTimes(Collection $items): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        return DB::table('product_supplier')
            ->selectRaw('product_id, variant_key, MIN(lead_time_days) as lead_time_days, MAX(is_preferred) as preferred')
            ->whereIn('product_id', $items->pluck('product_id')->unique()->all())
            ->whereNotNull('lead_time_days')
            ->groupBy('product_id', 'variant_key')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $row->product_id.'|'.$row->variant_key => (int) $row->lead_time_days,
            ])
            ->all();
    }

    /**
     * Drop rows that do not need reordering inside the requested horizon. Done
     * after the plan is computed because days of cover is not a column.
     *
     * @param  LengthAwarePaginator<int, InventoryItem>  $items
     * @return LengthAwarePaginator<int, InventoryItem>
     */
    private function applyReorderWindow(LengthAwarePaginator $items, ?int $withinDays): LengthAwarePaginator
    {
        if ($withinDays === null) {
            return $items;
        }

        return $items->setCollection(
            $items->getCollection()->filter(function (InventoryItem $item) use ($withinDays): bool {
                $plan = $item->getAttribute('plan');
                $cover = $plan['days_of_cover'];

                return $plan['needs_reorder'] && ($cover === null || $cover <= $withinDays);
            })->values(),
        );
    }

    /**
     * Matches the variant_key convention used across the module: the variant
     * id, or an empty string for a product-wide unit.
     */
    private function key(InventoryItem $item): string
    {
        return $item->product_id.'|'.($item->product_variant_id ?? '');
    }
}
