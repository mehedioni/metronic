<?php

namespace Modules\Inventory\Models;

use App\Core\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Inventory\Database\Factories\ProductVariantFactory;
use Modules\Inventory\Enums\RecordStatus;
use Modules\Inventory\Policies\ProductVariantPolicy;

/**
 * A stockable variation of a product. "options" holds the option name/value
 * pairs (e.g. {"size": "M", "color": "Red"}) so any option set works without
 * a schema change.
 */
#[Fillable([
    'product_id', 'sku', 'name', 'options', 'cost_price', 'selling_price',
    'low_stock_threshold', 'status',
])]
#[UsePolicy(ProductVariantPolicy::class)]
#[UseFactory(ProductVariantFactory::class)]
class ProductVariant extends BaseModel
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => RecordStatus::Active->value,
        'low_stock_threshold' => 0,
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'low_stock_threshold' => 'integer',
            'status' => RecordStatus::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryItem(): HasOne
    {
        return $this->hasOne(InventoryItem::class, 'product_variant_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Active);
    }
}
