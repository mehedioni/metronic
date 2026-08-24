<?php

namespace Modules\Inventory\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Support\QuerySorter;

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
            ->with(['product:id,name,sku,category_id,primary_supplier_id,low_stock_threshold', 'product.category:id,name', 'variant:id,sku,name'])
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
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * @param  array{type?: string|null, product_id?: string|null, product_variant_id?: string|null, supplier_id?: string|null, user_id?: int|null, from?: string|null, to?: string|null, per_page?: int|null}  $filters
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
