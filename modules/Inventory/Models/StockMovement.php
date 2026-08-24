<?php

namespace Modules\Inventory\Models;

use App\Core\BaseUuidModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Policies\StockMovementPolicy;
use Modules\Inventory\Support\StockableUnit;

/**
 * Append-only audit row for a single on-hand stock change.
 *
 * Rows are never updated or deleted — a mistake is corrected by recording a
 * compensating movement, which is why this model has no soft deletes.
 * Reservations are not movements: they change quantity_reserved only and
 * leave on-hand untouched.
 */
#[Fillable([
    'product_id', 'product_variant_id', 'supplier_id', 'type', 'quantity',
    'quantity_before', 'quantity_after', 'unit_cost', 'reference_type',
    'reference_id', 'reason', 'user_id',
])]
#[UsePolicy(StockMovementPolicy::class)]
class StockMovement extends BaseUuidModel
{
    /**
     * Matches the microsecond precision of the timestamp columns so ordering
     * by created_at is stable.
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'unit_cost' => 'decimal:2',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): StockableUnit
    {
        return new StockableUnit($this->product_id, $this->product_variant_id);
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $query->when($type, fn (Builder $q) => $q->where('type', $type));
    }

    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to.' 23:59:59'));
    }

    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('quantity', '<', 0);
    }
}
