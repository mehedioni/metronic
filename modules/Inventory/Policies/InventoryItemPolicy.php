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

    public function adjust(User $user, InventoryItem $inventoryItem): bool
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
