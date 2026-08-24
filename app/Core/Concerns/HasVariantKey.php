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
 */
trait HasVariantKey
{
    protected static function bootHasVariantKey(): void
    {
        static::saving(function (self $model): void {
            $model->variant_key = (string) ($model->product_variant_id ?? '');
        });
    }
}
