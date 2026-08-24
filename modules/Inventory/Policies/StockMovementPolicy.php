<?php

namespace Modules\Inventory\Policies;

use App\Core\Support\Permissions;
use App\Models\User;
use Modules\Inventory\Models\StockMovement;

/**
 * The ledger is append-only: it can be read, never written or removed through the API.
 */
class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::INVENTORY_VIEW);
    }

    public function view(User $user, StockMovement $stockMovement): bool
    {
        return $user->can(Permissions::INVENTORY_VIEW);
    }
}
