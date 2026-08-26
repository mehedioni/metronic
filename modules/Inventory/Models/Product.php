<?php

namespace Modules\Inventory\Models;

use App\Core\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Modules\Inventory\Database\Factories\ProductFactory;
use Modules\Inventory\Enums\ProductStatus;
use Modules\Inventory\Enums\ProductType;
use Modules\Inventory\Policies\ProductPolicy;

#[Fillable([
    'category_id', 'primary_supplier_id', 'name', 'slug', 'sku', 'description',
    'type', 'status', 'cost_price', 'selling_price', 'low_stock_threshold',
    'meta',
])]
#[UsePolicy(ProductPolicy::class)]
#[UseFactory(ProductFactory::class)]
class Product extends BaseModel
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The public identifier is generated, never supplied, so it stays out of
     * the fillable list and cannot be set through a request.
     *
     * Generated in the constructor rather than from a "creating" event,
     * because events get muted — seeders run inside Model::withoutEvents() —
     * and a NOT NULL unique column whose value depends on an event fires an
     * insert error the first time anything mutes them. Hydration from the
     * database replaces the attribute bag wholesale, so this never overwrites
     * a stored uuid.
     *
     * Random (v4) on purpose. A time-ordered uuid would carry the creation
     * moment and sit near its neighbours, which gives back some of what a
     * public identifier is here to withhold.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->attributes['uuid'] ??= Str::uuid()->toString();
    }

    /**
     * Resolve a product from its public identifier.
     *
     * Nothing binds routes to it today — links use the integer key — but any
     * outward-facing lookup (an export, an integration, a catalogue URL)
     * should go through this rather than exposing the key.
     */
    public static function findByUuid(string $uuid): ?self
    {
        return static::query()->where('uuid', $uuid)->first();
    }

    protected $attributes = [
        'type' => ProductType::Simple->value,
        'status' => ProductStatus::Active->value,
        'low_stock_threshold' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'low_stock_threshold' => 'integer',
            'meta' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function primarySupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'primary_supplier_id');
    }

    /**
     * Every image for this product, in the order they were arranged.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->ordered();
    }

    /**
     * The one image a list row or a card shows. Prefers the image flagged
     * primary, and falls back to the first in order so a product with images
     * always has something to show.
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier')
            ->using(ProductSupplier::class)
            ->withPivot([
                'id', 'product_variant_id', 'supplier_sku', 'unit_cost',
                'minimum_order_quantity', 'lead_time_days', 'is_preferred',
            ])
            ->withTimestamps();
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(
            fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhereHas('variants', fn (Builder $variant) => $variant->where('sku', 'like', "%{$term}%")),
        ));
    }

    /**
     * Products where at least one stockable unit sits at or below its
     * threshold. Compared column-to-column so no per-row query is needed.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereHas(
            'inventoryItems',
            fn (Builder $item) => $item->whereColumn(
                'inventory_items.quantity_on_hand',
                '<=',
                'products.low_stock_threshold',
            ),
        );
    }

    public function scopeForSupplier(Builder $query, ?string $supplierId): Builder
    {
        return $query->when($supplierId, fn (Builder $q) => $q->where(
            fn (Builder $inner) => $inner
                ->where('primary_supplier_id', $supplierId)
                ->orWhereHas('suppliers', fn (Builder $s) => $s->where('suppliers.id', $supplierId)),
        ));
    }

    public function hasVariants(): bool
    {
        return $this->type === ProductType::Variable;
    }
}
