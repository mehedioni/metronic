<?php

namespace Modules\Inventory\Models;

use App\Core\Concerns\HasVariantKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Policies\InventoryItemPolicy;
use Modules\Inventory\Support\StockableUnit;

/**
 * Current stock for one stockable unit. This is the only place a quantity is
 * read from; the stock_movements ledger explains how it got there.
 *
 * Never written outside a transaction that also appends the matching ledger
 * row — see Modules\Inventory\Services\InventoryService.
 */
#[Fillable(['product_id', 'product_variant_id', 'quantity_on_hand', 'quantity_reserved'])]
#[UsePolicy(InventoryItemPolicy::class)]
class InventoryItem extends Model
{
    use HasVariantKey;

    protected $attributes = [
        'quantity_on_hand' => 0,
        'quantity_reserved' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Stock that may still be promised to a new order.
     */
    public function availableQuantity(): int
    {
        return $this->quantity_on_hand - $this->quantity_reserved;
    }

    public function unit(): StockableUnit
    {
        return new StockableUnit($this->product_id, $this->product_variant_id);
    }

    public function scopeForUnit(Builder $query, StockableUnit $unit): Builder
    {
        return $query
            ->where('product_id', $unit->productId)
            ->where('variant_key', $unit->variantKey());
    }

    /**
     * Units at or below the threshold configured on their variant, falling
     * back to the product threshold when the unit has no variant.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereHas(
            'product',
            fn (Builder $product) => $product->whereColumn(
                'inventory_items.quantity_on_hand',
                '<=',
                'products.low_stock_threshold',
            ),
        );
    }
}
