<?php

namespace Modules\Inventory\Models;

use App\Core\BaseUuidModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\OrderItemFactory;
use Modules\Inventory\Support\StockableUnit;

#[Fillable([
    'order_id', 'product_id', 'product_variant_id', 'quantity',
    'quantity_shipped', 'unit_price', 'line_total',
])]
#[UseFactory(OrderItemFactory::class)]
class OrderItem extends BaseUuidModel
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $attributes = [
        'quantity_shipped' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_shipped' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function unit(): StockableUnit
    {
        return new StockableUnit($this->product_id, $this->product_variant_id);
    }

    public function outstandingQuantity(): int
    {
        return max(0, $this->quantity - $this->quantity_shipped);
    }
}
