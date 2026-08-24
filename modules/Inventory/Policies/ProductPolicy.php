<?php

namespace Modules\Inventory\Policies;

use App\Core\Support\Permissions;
use App\Models\User;
use Modules\Inventory\Models\Product;

/**
 * Product access.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::PRODUCTS_VIEW);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can(Permissions::PRODUCTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::PRODUCTS_CREATE);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can(Permissions::PRODUCTS_UPDATE);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can(Permissions::PRODUCTS_DELETE);
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->can(Permissions::PRODUCTS_UPDATE);
    }
}
