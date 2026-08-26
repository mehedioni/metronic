<?php

namespace Modules\Inventory\Models;

use App\Core\Services\FileStorageService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stored image for a product, or for one of its variants.
 *
 * The row holds a relative path; the URL is derived on read through
 * FileStorageService, so nothing in the database has to change when the
 * storage provider does.
 */
#[Fillable([
    'product_id', 'product_variant_id', 'disk', 'path', 'original_name',
    'mime_type', 'size', 'sort_order', 'is_primary',
])]
class ProductImage extends Model
{
    protected $attributes = [
        'sort_order' => 0,
        'is_primary' => false,
    ];

    /**
     * The URL travels with every serialised image, so a Vue component never
     * builds one and never learns which disk is in use.
     *
     * @var array<int, string>
     */
    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
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

    /**
     * Resolved against the disk the file was written to, falling back to the
     * configured one for rows saved before the disk was recorded.
     */
    public function url(): Attribute
    {
        return Attribute::get(
            fn (): ?string => app(FileStorageService::class)->url($this->path, $this->disk),
        )->shouldCache();
    }

    /**
     * The gallery order the user arranged. Deliberately independent of
     * is_primary: promoting an image marks which one represents the product,
     * it does not silently drag it to the front of the gallery.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }
}
