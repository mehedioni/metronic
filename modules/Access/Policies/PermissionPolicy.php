<?php

namespace Modules\Access\Policies;

use App\Core\Support\Permissions;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Permissions themselves are a fixed catalogue (App\Core\Support\Permissions)
 * seeded by the application, so they are readable but not creatable through
 * the API.
 */
class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::PERMISSIONS_VIEW);
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->can(Permissions::PERMISSIONS_VIEW);
    }

    public function manage(User $user): bool
    {
        return $user->can(Permissions::PERMISSIONS_MANAGE);
    }
}
