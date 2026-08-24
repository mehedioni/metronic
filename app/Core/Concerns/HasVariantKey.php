<?php

namespace App\Core\Concerns;

/**
 * Keeps a non-null "variant_key" column in sync with a nullable
 * "product_variant_id" column.
 *
 * Composite unique indexes cannot rely on the nullable column directly:
 * MySQL treats NULLs in a unique index as distinct, so ('p1', NULL) could be
 * inserted twice. The mirrored key stores '' for "no variant" and makes the
 * index enforce what it claims to.
 *
 * The mirroring happens on assignment rather than only on a saving event,
 * because model events can be muted — seeders and Model::withoutEvents() do
 * exactly that — and a wrong variant_key silently breaks both the unique
 * index and every lookup that filters on it. The saving hook stays as a
 * backstop for a key set directly on the attribute bag.
 */
trait HasVariantKey
{
    protected static function bootHasVariantKey(): void
    {
        static::saving(function (self $model): void {
            $model->attributes['variant_key'] = $model->variantKeyFor(
                $model->attributes['product_variant_id'] ?? null,
            );
        });
    }

    public function setProductVariantIdAttribute(?string $value): void
    {
        $this->attributes['product_variant_id'] = $value;
        $this->attributes['variant_key'] = $this->variantKeyFor($value);
    }

    /**
     * Non-null mirror of a variant id: the id itself, or '' for a
     * product-wide row.
     */
    private function variantKeyFor(?string $variantId): string
    {
        return (string) ($variantId ?? '');
    }
}
