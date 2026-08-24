<?php

namespace Modules\Inventory\Policies;

use App\Core\Support\Permissions;
use App\Models\User;
use Modules\Inventory\Models\Category;

/**
 * Category access. Deletion is additionally guarded by CategoryService, which refuses to orphan products.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::CATEGORIES_VIEW);
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can(Permissions::CATEGORIES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::CATEGORIES_CREATE);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can(Permissions::CATEGORIES_UPDATE);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can(Permissions::CATEGORIES_DELETE);
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->can(Permissions::CATEGORIES_UPDATE);
    }
}
