<?php

namespace Modules\Inventory\Services;

use App\Core\Support\QuerySorter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Exceptions\RestrictedDeletionException;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductSupplier;
use Modules\Inventory\Models\ProductVariant;

class ProductService
{
    /**
     * Columns the product list may be ordered by.
     */
    private const SORTABLE = [
        'name' => 'name',
        'sku' => 'sku',
        'status' => 'status',
        'selling_price' => 'selling_price',
        'cost_price' => 'cost_price',
        'created_at' => 'created_at',
    ];

    /**
     * @param  array{search?: string|null, category_id?: string|null, supplier_id?: string|null, status?: string|null, low_stock?: bool|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Product::query()
            ->with([
                'category:id,name',
                'primarySupplier:id,company_name',
                'variants',
                'suppliers:id,company_name',
                'inventoryItems:id,product_id,product_variant_id,quantity_on_hand,quantity_reserved',
                // Primary for list thumbnails; all images for the edit drawer.
                'primaryImage',
                'images',
            ])
            ->withCount('variants')
            ->search($filters['search'] ?? null)
            ->forSupplier($filters['supplier_id'] ?? null)
            ->when($filters['category_id'] ?? null, fn ($query, $category) => $query->where('category_id', $category))
            ->when($filters['status'] ?? null, function ($query, $status) {
                $val = strtolower((string) $status);
                $map = [
                    'live' => 'active',
                    'draft' => 'inactive',
                ];

                return $query->where('status', $map[$val] ?? $val);
            })
            ->when($filters['low_stock'] ?? false, fn ($query) => $query->lowStock())
            ->tap(fn ($query) => QuerySorter::apply(
                $query,
                $filters['sort'] ?? null,
                $filters['direction'] ?? null,
                self::SORTABLE,
            ))
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();
    }

    /**
     * Create a product plus, optionally, its variants and supplier terms.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $product = Product::create($this->attributes($data));

            $this->syncVariants($product, $data['variants'] ?? []);
            $this->syncSuppliers($product, $data['suppliers'] ?? []);

            return $product->load(['variants', 'suppliers']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $product->update($this->attributes($data));

            if (array_key_exists('variants', $data)) {
                $this->syncVariants($product, $data['variants']);
            }

            if (array_key_exists('suppliers', $data)) {
                $this->syncSuppliers($product, $data['suppliers']);
            }

            return $product->refresh()->load(['variants', 'suppliers']);
        });
    }

    /**
     * Products referenced by stock or sales history are archived, not deleted.
     */
    public function delete(Product $product): void
    {
        if ($product->stockMovements()->exists()) {
            throw RestrictedDeletionException::because(
                "Product \"{$product->name}\"",
                'it has stock movement history',
            );
        }

        $product->delete();
    }

    /**
     * Upsert the given variants and remove variants no longer listed, unless
     * they already carry stock history.
     *
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $keptIds = [];

        foreach ($variants as $variant) {
            $model = isset($variant['id'])
                ? $product->variants()->whereKey($variant['id'])->firstOrFail()
                : new ProductVariant(['product_id' => $product->getKey()]);

            $model->fill($variant);
            $model->product_id = $product->getKey();
            $model->save();

            $keptIds[] = $model->getKey();
        }

        $product->variants()
            ->whereKeyNot($keptIds)
            ->whereDoesntHave('stockMovements')
            ->get()
            ->each->delete();
    }

    /**
     * Replace the product's supplier terms. Written through the pivot model
     * rather than attach() so UUID and variant_key generation stay in one
     * place.
     *
     * @param  array<int, array<string, mixed>>  $suppliers
     */
    private function syncSuppliers(Product $product, array $suppliers): void
    {
        ProductSupplier::query()->where('product_id', $product->getKey())->delete();

        foreach ($suppliers as $supplier) {
            ProductSupplier::create([
                'product_id' => $product->getKey(),
                'product_variant_id' => $supplier['product_variant_id'] ?? null,
                'supplier_id' => $supplier['supplier_id'],
                'supplier_sku' => $supplier['supplier_sku'] ?? null,
                'unit_cost' => $supplier['unit_cost'] ?? null,
                'minimum_order_quantity' => $supplier['minimum_order_quantity'] ?? null,
                'lead_time_days' => $supplier['lead_time_days'] ?? null,
                'is_preferred' => $supplier['is_preferred'] ?? false,
            ]);
        }
    }

    /**
     * Strip the nested collections that are persisted separately.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return collect($data)->except(['variants', 'suppliers', 'images'])->all();
    }
}
