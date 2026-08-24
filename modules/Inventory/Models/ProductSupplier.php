<?php

namespace Modules\Inventory\Models;

use App\Core\BaseUuidModel;
use App\Core\Concerns\HasVariantKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;
use Modules\Inventory\Support\StockableUnit;

/**
 * Pivot carrying the commercial terms of one product (optionally one variant)
 * at one supplier. Modelled explicitly rather than as a bare pivot so the
 * per-supplier terms can grow without restructuring the relationship.
 */
#[Fillable([
    'product_id', 'product_variant_id', 'supplier_id', 'supplier_sku',
    'unit_cost', 'minimum_order_quantity', 'lead_time_days', 'is_preferred',
])]
class ProductSupplier extends BaseUuidModel
{
    use AsPivot, HasVariantKey;

    protected $table = 'product_supplier';

    protected $attributes = [
        'is_preferred' => false,
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'minimum_order_quantity' => 'integer',
            'lead_time_days' => 'integer',
            'is_preferred' => 'boolean',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function unit(): StockableUnit
    {
        return new StockableUnit($this->product_id, $this->product_variant_id);
    }
}
