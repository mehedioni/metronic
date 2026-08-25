<?php

namespace Modules\Inventory\Support;

/**
 * Value object identifying one stockable unit: a product, or a product plus a
 * specific variant. Every inventory operation is expressed in terms of this
 * pair so services never have to juggle "is this a variant or not".
 */
final readonly class StockableUnit
{
    public function __construct(
        public int $productId,
        public ?int $productVariantId = null,
    ) {}

    /**
     * Non-null mirror of the variant id, matching the "variant_key" column.
     */
    public function variantKey(): string
    {
        return (string) ($this->productVariantId ?? '');
    }

    public function equals(self $other): bool
    {
        return $this->productId === $other->productId
            && $this->variantKey() === $other->variantKey();
    }

    /**
     * Stable string key, useful for grouping/deduplicating units in memory.
     */
    public function key(): string
    {
        return $this->productId.':'.$this->variantKey();
    }
}
