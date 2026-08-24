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
use Modules\Inventory\Database\Factories\ShipmentFactory;
use Modules\Inventory\Enums\ShipmentStatus;
use Modules\Inventory\Policies\ShipmentPolicy;

/**
 * Outbound shipment for an order. Carrier and tracking are plain columns so a
 * provider integration can be added later without touching this schema.
 *
 * "shipped_at" doubles as the idempotency guard for the stock deduction: it is
 * set in the same transaction that writes the movements, so a repeated
 * dispatch is rejected instead of deducting twice.
 */
#[Fillable([
    'shipment_number', 'order_id', 'status', 'carrier', 'tracking_number',
    'notes', 'created_by',
])]
#[UsePolicy(ShipmentPolicy::class)]
#[UseFactory(ShipmentFactory::class)]
class Shipment extends BaseModel
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => ShipmentStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(
            fn (Builder $inner) => $inner
                ->where('shipment_number', 'like', "%{$term}%")
                ->orWhere('tracking_number', 'like', "%{$term}%")
                ->orWhereHas('order', fn (Builder $o) => $o->where('order_number', 'like', "%{$term}%")),
        ));
    }

    public function hasDispatched(): bool
    {
        return $this->shipped_at !== null;
    }
}
