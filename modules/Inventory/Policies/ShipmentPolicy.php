<?php

namespace Modules\Inventory\Policies;

use App\Core\Support\Permissions;
use App\Models\User;
use Modules\Inventory\Models\Shipment;

/**
 * Shipment permissions. Dispatching moves stock and is therefore an update, not a create.
 */
class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::SHIPMENTS_VIEW);
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $user->can(Permissions::SHIPMENTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::SHIPMENTS_CREATE);
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->can(Permissions::SHIPMENTS_UPDATE);
    }

    public function dispatch(User $user, Shipment $shipment): bool
    {
        return $user->can(Permissions::SHIPMENTS_UPDATE);
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->can(Permissions::SHIPMENTS_DELETE);
    }
}
