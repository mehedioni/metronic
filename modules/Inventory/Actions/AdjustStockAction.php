<?php

namespace Modules\Inventory\Actions;

use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Support\MovementContext;
use Modules\Inventory\Support\StockableUnit;

/**
 * Manual stock correction: opening stock, damage, transfers, write-offs.
 * Everything a user can do to stock outside receiving and fulfilment goes
 * through here so it always lands in the ledger.
 */
class AdjustStockAction
{
    public function __construct(private InventoryService $inventory) {}

    public function handle(
        StockableUnit $unit,
        StockMovementType $type,
        int $quantity,
        ?string $reason = null,
        ?int $userId = null,
    ): StockMovement {
        return $this->inventory->record(
            $unit,
            $type,
            $quantity,
            new MovementContext(reason: $reason, userId: $userId),
        );
    }
}
