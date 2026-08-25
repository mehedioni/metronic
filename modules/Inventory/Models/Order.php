<?php

namespace Modules\Inventory\Models;

use App\Core\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\OrderFactory;
use Modules\Inventory\Policies\OrderPolicy;
use Modules\Inventory\Support\OrderStatus;
use Modules\Inventory\Support\OrderStatuses;

/**
 * Sales order. Stock is reserved on confirmation and deducted when the order
 * is fulfilled.
 *
 * The status is stored as the configured id (config/orders.php) and read back
 * through the "status" accessor, which hands out the value object carrying the
 * transition table and the flags those effects are bound to.
 */
#[Fillable([
    'order_number', 'customer_id', 'customer_name', 'customer_email', 'customer_phone',
    'delivery_address', 'status_id', 'subtotal', 'discount_total', 'tax_total',
    'total', 'currency', 'notes', 'created_by',
])]
#[UsePolicy(OrderPolicy::class)]
#[UseFactory(OrderFactory::class)]
class Order extends BaseModel
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $attributes = [
        'currency' => 'USD',
    ];

    /**
     * The status object travels with every serialised order, so the frontend
     * renders a label and a badge colour without knowing the lifecycle.
     *
     * @var array<int, string>
     */
    protected $appends = ['status'];

    protected function casts(): array
    {
        return [
            'status_id' => 'integer',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * A new order starts in the configured default status.
     */
    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->status_id ??= OrderStatuses::default()->id;
        });
    }

    /**
     * The configured status this order is in.
     */
    public function status(): Attribute
    {
        return Attribute::get(
            fn (): OrderStatus => OrderStatuses::find((int) $this->status_id),
        )->shouldCache();
    }

    /**
     * Move the order to a status, by id, key or object. Callers that must
     * respect the transition table go through an Action; this only writes.
     */
    public function setStatus(OrderStatus|int|string $status): void
    {
        $resolved = OrderStatuses::resolve($status);

        if ($resolved === null) {
            throw new \InvalidArgumentException('Unknown order status.');
        }

        $this->status_id = $resolved->id;
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

    /**
     * Narrow to one status, given an id, a key or the object itself.
     */
    public function scopeWithStatus(Builder $query, OrderStatus|int|string|null $status): Builder
    {
        $resolved = OrderStatuses::resolve($status);

        return $query->when($resolved, fn (Builder $q) => $q->where('orders.status_id', $resolved->id));
    }

    /**
     * Exclude one status — the orders list uses it to leave quotes out, since
     * those have their own screen.
     */
    public function scopeWithoutStatus(Builder $query, OrderStatus|int|string|null $status): Builder
    {
        $resolved = OrderStatuses::resolve($status);

        return $query->when($resolved, fn (Builder $q) => $q->where('orders.status_id', '!=', $resolved->id));
    }

    /**
     * Orders that count as trade: everything a void status excludes.
     */
    public function scopeBillable(Builder $query): Builder
    {
        return $query->whereIn('orders.status_id', OrderStatuses::billableIds());
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
