<?php

namespace Modules\Inventory\Models;

use App\Core\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\OrderFactory;
use Modules\Inventory\Enums\OrderStatus;
use Modules\Inventory\Policies\OrderPolicy;

/**
 * Sales order. Stock is reserved on confirmation and deducted when the order
 * is fulfilled — see Modules\Inventory\Enums\OrderStatus for the transition
 * table that drives those effects.
 */
#[Fillable([
    'order_number', 'customer_id', 'customer_name', 'customer_email', 'customer_phone',
    'delivery_address', 'status', 'subtotal', 'discount_total', 'tax_total',
    'total', 'currency', 'notes', 'created_by',
])]
#[UsePolicy(OrderPolicy::class)]
#[UseFactory(OrderFactory::class)]
class Order extends BaseModel
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => OrderStatus::Draft->value,
        'currency' => 'USD',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The customer record this order belongs to. Null for a walk-in sale,
     * where the customer_name snapshot is all there is.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(
            fn (Builder $inner) => $inner
                ->where('order_number', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('customer_email', 'like', "%{$term}%"),
        ));
    }

    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to.' 23:59:59'));
    }

    /**
     * Recalculate money columns from the current line items.
     */
    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('line_total');

        $this->forceFill([
            'subtotal' => $subtotal,
            'total' => $subtotal - (float) $this->discount_total + (float) $this->tax_total,
        ])->save();
    }

    public function isFullyFulfilled(): bool
    {
        return $this->items->every(
            fn (OrderItem $item) => $item->quantity_fulfilled >= $item->quantity,
        );
    }
}
