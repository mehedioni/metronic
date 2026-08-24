<?php

namespace Modules\Access\Policies;

use App\Core\Support\Permissions;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::USERS_VIEW);
    }

    public function view(User $user, User $target): bool
    {
        return $user->can(Permissions::USERS_VIEW) || $user->is($target);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::USERS_CREATE);
    }

    public function update(User $user, User $target): bool
    {
        return $user->can(Permissions::USERS_UPDATE);
    }

    /**
     * Deactivating yourself would lock you out of the panel, so it is refused
     * here rather than only in the UI.
     */
    public function deactivate(User $user, User $target): bool
    {
        return $user->can(Permissions::USERS_UPDATE) && ! $user->is($target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can(Permissions::USERS_DELETE) && ! $user->is($target);
    }

    /**
     * Role assignment is gated separately from profile edits: granting roles
     * is how privilege escalation happens.
     */
    public function assignRoles(User $user, User $target): bool
    {
        return $user->can(Permissions::PERMISSIONS_MANAGE) || $user->can(Permissions::ROLES_UPDATE);
    }
}
