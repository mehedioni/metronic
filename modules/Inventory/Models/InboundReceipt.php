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
use Modules\Inventory\Database\Factories\InboundReceiptFactory;
use Modules\Inventory\Enums\InboundReceiptStatus;
use Modules\Inventory\Enums\InboundSource;
use Modules\Inventory\Policies\InboundReceiptPolicy;

/**
 * A receiving document. Stock only moves when the receipt transitions into
 * "received" — "processed_at" is the guard that makes that transition
 * idempotent, so re-processing can never double-count.
 */
#[Fillable([
    'reference_number', 'supplier_id', 'source', 'status', 'received_date',
    'received_by', 'notes',
])]
#[UsePolicy(InboundReceiptPolicy::class)]
#[UseFactory(InboundReceiptFactory::class)]
class InboundReceipt extends BaseModel
{
    /** @use HasFactory<InboundReceiptFactory> */
    use HasFactory;

    protected $attributes = [
        'source' => InboundSource::Supplier->value,
        'status' => InboundReceiptStatus::Draft->value,
    ];

    protected function casts(): array
    {
        return [
            'source' => InboundSource::class,
            'status' => InboundReceiptStatus::class,
            'received_date' => 'date',
            'processed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InboundReceiptItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
            ->where('reference_type', self::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(
            fn (Builder $inner) => $inner
                ->where('reference_number', 'like', "%{$term}%")
                ->orWhereHas('supplier', fn (Builder $s) => $s->where('company_name', 'like', "%{$term}%")),
        ));
    }

    /**
     * True once this receipt has already moved stock.
     */
    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
