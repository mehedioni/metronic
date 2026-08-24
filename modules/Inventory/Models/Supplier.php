<?php

namespace Modules\Inventory\Models;

use App\Core\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\SupplierFactory;
use Modules\Inventory\Enums\RecordStatus;
use Modules\Inventory\Policies\SupplierPolicy;

#[Fillable([
    'code', 'company_name', 'contact_name', 'email', 'phone', 'phone_alt', 'website',
    'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country',
    'tax_number', 'payment_terms', 'notes', 'status',
])]
#[UsePolicy(SupplierPolicy::class)]
#[UseFactory(SupplierFactory::class)]
class Supplier extends BaseModel
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => RecordStatus::Active->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_supplier')
            ->using(ProductSupplier::class)
            ->withPivot([
                'id', 'product_variant_id', 'supplier_sku', 'unit_cost',
                'minimum_order_quantity', 'lead_time_days', 'is_preferred',
            ])
            ->withTimestamps();
    }

    /**
     * Products whose primary supplier is this supplier.
     */
    public function primaryProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'primary_supplier_id');
    }

    public function inboundReceipts(): HasMany
    {
        return $this->hasMany(InboundReceipt::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Active);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(
            fn (Builder $inner) => $inner
                ->where('company_name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%"),
        ));
    }

    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active;
    }
}
