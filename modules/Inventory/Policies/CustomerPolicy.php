<?php

namespace Modules\Inventory\Policies;

use App\Core\Support\Permissions;
use App\Models\User;
use Modules\Inventory\Models\Customer;

/**
 * Customer access. Customers with order history are deactivated rather than
 * deleted by CustomerService, so the orders keep resolving.
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::CUSTOMERS_VIEW);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can(Permissions::CUSTOMERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::CUSTOMERS_CREATE);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can(Permissions::CUSTOMERS_UPDATE);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can(Permissions::CUSTOMERS_DELETE);
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->can(Permissions::CUSTOMERS_UPDATE);
    }
}
