<?php

namespace Modules\Access\Services;

use App\Core\Support\Roles;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Access\Exceptions\RoleEscalationException;
use Spatie\Permission\Models\Role;

class UserService
{
    /**
     * @param  array{search?: string|null, role?: string|null, is_active?: bool|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return User::query()
            ->with('roles:id,name')
            ->search($filters['search'] ?? null)
            ->when(
                $filters['role'] ?? null,
                fn ($query, $role) => $query->whereHas('roles', fn ($q) => $q->where('name', $role)),
            )
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', (bool) $filters['is_active']),
            )
            ->latest()
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): User
    {
        $roles = $data['roles'] ?? [];
        $this->assertMayAssignRoles($actor, $roles);

        return DB::transaction(function () use ($data, $roles): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $user->syncRoles($roles);

            return $user->load('roles:id,name');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        if (array_key_exists('roles', $data)) {
            $this->assertMayAssignRoles($actor, $data['roles']);
            $this->assertNotDemotingLastSuperAdmin($user, $data['roles']);
        }

        return DB::transaction(function () use ($user, $data): User {
            $user->update(collect($data)->only(['name', 'email', 'is_active'])->all());

            if (! empty($data['password'])) {
                $user->update(['password' => $data['password']]);
            }

            if (array_key_exists('roles', $data)) {
                $user->syncRoles($data['roles']);
            }

            return $user->refresh()->load('roles:id,name');
        });
    }

    public function setActive(User $user, bool $isActive): User
    {
        $user->update(['is_active' => $isActive]);

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    /**
     * An actor may only grant roles whose permissions they already hold.
     * Super Admin bypasses this via Gate::before, and only a Super Admin may
     * grant Super Admin.
     *
     * @param  array<int, string>  $roleNames
     */
    private function assertMayAssignRoles(User $actor, array $roleNames): void
    {
        if ($actor->hasRole(Roles::SUPER_ADMIN)) {
            return;
        }

        foreach ($roleNames as $roleName) {
            if ($roleName === Roles::SUPER_ADMIN) {
                throw RoleEscalationException::forRole($roleName);
            }

            $permissions = Role::query()
                ->where('name', $roleName)
                ->with('permissions:id,name')
                ->first()
                ?->permissions
                ->pluck('name')
                ->all() ?? [];

            foreach ($permissions as $permission) {
                if (! $actor->can($permission)) {
                    throw RoleEscalationException::forRole($roleName);
                }
            }
        }
    }

    /**
     * Removing the last Super Admin would leave the application with no way
     * back in.
     *
     * @param  array<int, string>  $roleNames
     */
    private function assertNotDemotingLastSuperAdmin(User $user, array $roleNames): void
    {
        if (! $user->hasRole(Roles::SUPER_ADMIN) || in_array(Roles::SUPER_ADMIN, $roleNames, true)) {
            return;
        }

        $remaining = User::query()->whereHas('roles', fn ($query) => $query->where('name', Roles::SUPER_ADMIN))->count();

        if ($remaining <= 1) {
            throw RoleEscalationException::forRole(Roles::SUPER_ADMIN);
        }
    }
}
