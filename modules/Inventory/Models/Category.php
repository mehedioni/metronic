<?php

namespace Modules\Inventory\Models;

use App\Core\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\CategoryFactory;
use Modules\Inventory\Enums\RecordStatus;
use Modules\Inventory\Policies\CategoryPolicy;

#[Fillable(['parent_id', 'name', 'slug', 'description', 'status'])]
#[UsePolicy(CategoryPolicy::class)]
#[UseFactory(CategoryFactory::class)]
class Category extends BaseModel
{
    /** @use HasFactory<CategoryFactory> */
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Active);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(
            fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%"),
        ));
    }

    /**
     * Ids of this category and every descendant, used to reject circular
     * parent assignments.
     *
     * @return array<int, string>
     */
    public function descendantIds(): array
    {
        $ids = [$this->getKey()];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }
}
