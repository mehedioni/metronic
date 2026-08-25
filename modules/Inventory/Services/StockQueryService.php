<?php

namespace Modules\Inventory\Services;

use App\Core\Support\QuerySorter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockMovement;

/**
 * Read side of inventory: current stock levels and ledger history. Kept apart
 * from InventoryService so the write path stays small and obvious.
 */
class StockQueryService
{
    private const SORTABLE_ITEMS = [
        'quantity_on_hand' => 'quantity_on_hand',
        'quantity_reserved' => 'quantity_reserved',
        'updated_at' => 'updated_at',
    ];

    private const SORTABLE_MOVEMENTS = [
        'type' => 'type',
        'quantity' => 'quantity',
        'created_at' => 'created_at',
    ];

    /**
     * @param  array{search?: string|null, category_id?: string|null, supplier_id?: string|null, low_stock?: bool|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, InventoryItem>
     */
    public function paginateItems(array $filters): LengthAwarePaginator
    {
        return InventoryItem::query()
            ->with([
                'product:id,name,sku,category_id,primary_supplier_id,cost_price,selling_price,image_path,low_stock_threshold',
                'product.category:id,name',
                'product.primarySupplier:id,company_name,code',
                'variant:id,sku,name,selling_price,cost_price',
            ])
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->whereHas(
                'product',
                fn ($product) => $product->search($term),
            ))
            ->when($filters['category_id'] ?? null, fn ($query, $category) => $query->whereHas(
                'product',
                fn ($product) => $product->where('category_id', $category),
            ))
            ->when($filters['supplier_id'] ?? null, fn ($query, $supplier) => $query->whereHas(
                'product',
                fn ($product) => $product->forSupplier($supplier),
            ))
            ->when($filters['low_stock'] ?? false, fn ($query) => $query->lowStock())
            ->tap(fn ($query) => QuerySorter::apply(
                $query,
                $filters['sort'] ?? null,
                $filters['direction'] ?? null,
                self::SORTABLE_ITEMS,
                'quantity_on_hand',
                'asc',
            ))
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    /**
     * @return array{total_asset_value: float, total_products: int, in_stock: int, low_stock: int, out_of_stock: int}
     */
    public function summary(): array
    {
        $items = InventoryItem::query()
            ->join('products', 'inventory_items.product_id', '=', 'products.id')
            ->selectRaw('
                COALESCE(SUM(inventory_items.quantity_on_hand * products.selling_price), 0) as total_asset_value,
                COUNT(inventory_items.id) as total_products,
                COUNT(CASE WHEN inventory_items.quantity_on_hand > products.low_stock_threshold THEN 1 END) as in_stock,
                COUNT(CASE WHEN inventory_items.quantity_on_hand > 0 AND inventory_items.quantity_on_hand <= products.low_stock_threshold THEN 1 END) as low_stock,
                COUNT(CASE WHEN inventory_items.quantity_on_hand <= 0 THEN 1 END) as out_of_stock
            ')
            ->first();

        return [
            'total_asset_value' => (float) ($items->total_asset_value ?? 0),
            'total_products' => (int) ($items->total_products ?? 0),
            'in_stock' => (int) ($items->in_stock ?? 0),
            'low_stock' => (int) ($items->low_stock ?? 0),
            'out_of_stock' => (int) ($items->out_of_stock ?? 0),
        ];
    }

    /**
     * @param  array{type?: string|null, direction_flow?: string|null, product_id?: string|null, product_variant_id?: string|null, supplier_id?: string|null, user_id?: int|null, from?: string|null, to?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public function paginateMovements(array $filters): LengthAwarePaginator
    {
        return StockMovement::query()
            ->with([
                'product:id,name,sku',
                'variant:id,sku,name',
                'supplier:id,company_name',
                'user:id,name',
            ])
            ->ofType($filters['type'] ?? null)
            ->between($filters['from'] ?? null, $filters['to'] ?? null)
            ->when($filters['product_id'] ?? null, fn ($query, $product) => $query->where('product_id', $product))
            ->when($filters['product_variant_id'] ?? null, fn ($query, $variant) => $query->where('product_variant_id', $variant))
            ->when($filters['supplier_id'] ?? null, fn ($query, $supplier) => $query->where('supplier_id', $supplier))
            ->when($filters['user_id'] ?? null, fn ($query, $user) => $query->where('user_id', $user))
            // "Inbound" and "outbound" are directions, not types: several
            // types share each direction, and the sign of the quantity is the
            // authority on which one a row is.
            ->when(
                ($filters['direction_flow'] ?? null) === 'inbound',
                fn ($query) => $query->inbound(),
            )
            ->when(
                ($filters['direction_flow'] ?? null) === 'outbound',
                fn ($query) => $query->outbound(),
            )
            ->tap(fn ($query) => QuerySorter::apply(
                $query,
                $filters['sort'] ?? null,
                $filters['direction'] ?? null,
                self::SORTABLE_MOVEMENTS,
            ))
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }
}
