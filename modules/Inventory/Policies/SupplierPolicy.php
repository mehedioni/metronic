<?php

namespace Modules\Inventory\Policies;

use App\Core\Support\Permissions;
use App\Models\User;
use Modules\Inventory\Models\Supplier;

/**
 * Supplier access. Suppliers with receiving history are archived rather than deleted by SupplierService.
 */
class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::SUPPLIERS_VIEW);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can(Permissions::SUPPLIERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::SUPPLIERS_CREATE);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can(Permissions::SUPPLIERS_UPDATE);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can(Permissions::SUPPLIERS_DELETE);
    }

    public function restore(User $user, Supplier $supplier): bool
    {
        return $user->can(Permissions::SUPPLIERS_UPDATE);
    }
}
