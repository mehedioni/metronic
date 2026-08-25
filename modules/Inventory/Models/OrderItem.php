<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\OrderItemFactory;
use Modules\Inventory\Support\StockableUnit;

#[Fillable([
    'order_id', 'product_id', 'product_variant_id', 'quantity',
    'quantity_fulfilled', 'unit_price', 'unit_cost', 'line_total',
])]
#[UseFactory(OrderItemFactory::class)]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $attributes = [
        'quantity_fulfilled' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_fulfilled' => 'integer',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
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

    public function unit(): StockableUnit
    {
        return new StockableUnit($this->product_id, $this->product_variant_id);
    }

    /**
     * What these units cost the store, from the snapshot taken at intake.
     * Null when no cost price was known, which a margin report must treat as
     * unknown rather than as zero.
     */
    public function lineCost(): ?float
    {
        return $this->unit_cost === null
            ? null
            : round((float) $this->unit_cost * $this->quantity, 2);
    }

    public function outstandingQuantity(): int
    {
        return max(0, $this->quantity - $this->quantity_fulfilled);
    }
}
