<?php

namespace Modules\Inventory\Policies;

use App\Core\Support\Permissions;
use App\Models\User;
use Modules\Inventory\Models\InventoryItem;

/**
 * Stock levels are read-only except through an adjustment, which needs inventory.adjust.
 */
class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::INVENTORY_VIEW);
    }

    public function view(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can(Permissions::INVENTORY_VIEW);
    }

    /**
     * Adjusting is checked before the row exists.
     *
     * An adjustment names a product (and optionally a variant), not an
     * inventory_items row: InventoryService creates that row the first time a
     * unit holds stock. So the controller authorizes this against the class,
     * and Laravel then calls the policy with the user alone — the model
     * parameter has to be optional or the check dies with an
     * ArgumentCountError before the ability is ever evaluated.
     */
    public function adjust(User $user, ?InventoryItem $inventoryItem = null): bool
    {
        return $user->can(Permissions::INVENTORY_ADJUST);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::INVENTORY_CREATE);
    }

    public function delete(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can(Permissions::INVENTORY_DELETE);
    }
}
