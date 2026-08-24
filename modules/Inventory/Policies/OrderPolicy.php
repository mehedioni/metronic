<?php

namespace Modules\Inventory\Policies;

use App\Core\Support\Permissions;
use App\Models\User;
use Modules\Inventory\Models\Order;

/**
 * Order permissions. There is no orders.delete permission, so removing a draft requires orders.cancel.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::ORDERS_VIEW);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can(Permissions::ORDERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::ORDERS_CREATE);
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can(Permissions::ORDERS_UPDATE);
    }

    public function confirm(User $user, Order $order): bool
    {
        return $user->can(Permissions::ORDERS_UPDATE);
    }

    public function fulfill(User $user, Order $order): bool
    {
        return $user->can(Permissions::ORDERS_FULFILL);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->can(Permissions::ORDERS_CANCEL);
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->can(Permissions::ORDERS_CANCEL);
    }
}
