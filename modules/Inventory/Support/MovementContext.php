<?php

namespace Modules\Inventory\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Everything a stock movement records beyond the unit, type and quantity.
 * Passing one object keeps InventoryService's signature stable as audit
 * requirements grow.
 */
final readonly class MovementContext
{
    public function __construct(
        public ?string $supplierId = null,
        public ?Model $reference = null,
        public ?string $reason = null,
        public ?float $unitCost = null,
        public ?int $userId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'supplier_id' => $this->supplierId,
            'reference_type' => $this->reference ? $this->reference::class : null,
            'reference_id' => $this->reference?->getKey(),
            'reason' => $this->reason,
            'unit_cost' => $this->unitCost,
            'user_id' => $this->userId,
        ];
    }
}
