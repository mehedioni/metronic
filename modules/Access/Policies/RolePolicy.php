<?php

namespace Modules\Access\Policies;

use App\Core\Support\Permissions;
use App\Core\Support\Roles;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::ROLES_VIEW);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(Permissions::ROLES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::ROLES_CREATE);
    }

    /**
     * The Super Admin role is the recovery path into the application; it is
     * never editable through the UI, whatever permissions the actor holds.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can(Permissions::ROLES_UPDATE) && $role->name !== Roles::SUPER_ADMIN;
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(Permissions::ROLES_DELETE)
            && ! in_array($role->name, Roles::all(), true);
    }

    public function managePermissions(User $user, Role $role): bool
    {
        return $user->can(Permissions::PERMISSIONS_MANAGE) && $role->name !== Roles::SUPER_ADMIN;
    }
}
