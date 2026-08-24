<?php

namespace Modules\Access\Services;

use App\Core\Support\Permissions;
use App\Core\Support\Roles;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Access\Exceptions\RoleEscalationException;
use Spatie\Permission\Models\Role;

class RoleService
{
    /**
     * @param  array{search?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Role>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Role
    {
        $permissions = $data['permissions'] ?? [];
        $this->assertMayGrant($actor, $permissions);

        $role = Role::create(['name' => $data['name']]);
        $role->syncPermissions($permissions);

        return $role->load('permissions:id,name');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Role $role, array $data, User $actor): Role
    {
        if (array_key_exists('permissions', $data)) {
            $this->assertMayGrant($actor, $data['permissions']);
            $role->syncPermissions($data['permissions']);
        }

        if (! empty($data['name']) && ! in_array($role->name, Roles::all(), true)) {
            $role->update(['name' => $data['name']]);
        }

        return $role->refresh()->load('permissions:id,name');
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    /**
     * The full permission catalogue, grouped, for the role editor.
     *
     * @return array<string, array<int, string>>
     */
    public function catalogue(): array
    {
        return Permissions::groups();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function assertMayGrant(User $actor, array $permissions): void
    {
        if ($actor->hasRole(Roles::SUPER_ADMIN)) {
            return;
        }

        foreach ($permissions as $permission) {
            if (! $actor->can($permission)) {
                throw RoleEscalationException::forPermission($permission);
            }
        }
    }
}
