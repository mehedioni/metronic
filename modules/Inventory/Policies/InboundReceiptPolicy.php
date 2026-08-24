<?php

namespace Modules\Inventory\Policies;

use App\Core\Support\Permissions;
use App\Models\User;
use Modules\Inventory\Models\InboundReceipt;

/**
 * Receiving permissions. Receiving stock is an inventory.create action; reversing one is an adjustment.
 */
class InboundReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::INVENTORY_VIEW);
    }

    public function view(User $user, InboundReceipt $inboundReceipt): bool
    {
        return $user->can(Permissions::INVENTORY_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::INVENTORY_CREATE);
    }

    public function update(User $user, InboundReceipt $inboundReceipt): bool
    {
        return $user->can(Permissions::INVENTORY_CREATE);
    }

    public function receive(User $user, InboundReceipt $inboundReceipt): bool
    {
        return $user->can(Permissions::INVENTORY_CREATE);
    }

    public function cancel(User $user, InboundReceipt $inboundReceipt): bool
    {
        return $user->can(Permissions::INVENTORY_ADJUST);
    }

    public function delete(User $user, InboundReceipt $inboundReceipt): bool
    {
        return $user->can(Permissions::INVENTORY_DELETE);
    }
}
