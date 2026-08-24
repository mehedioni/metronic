<?php

namespace Modules\Inventory\Policies;

use App\Core\Support\Permissions;
use App\Models\User;
use Modules\Inventory\Models\ProductVariant;

/**
 * Variants are edited as part of their product, so they share the product permissions.
 */
class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::PRODUCTS_VIEW);
    }

    public function view(User $user, ProductVariant $productVariant): bool
    {
        return $user->can(Permissions::PRODUCTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::PRODUCTS_CREATE);
    }

    public function update(User $user, ProductVariant $productVariant): bool
    {
        return $user->can(Permissions::PRODUCTS_UPDATE);
    }

    public function delete(User $user, ProductVariant $productVariant): bool
    {
        return $user->can(Permissions::PRODUCTS_DELETE);
    }
}
