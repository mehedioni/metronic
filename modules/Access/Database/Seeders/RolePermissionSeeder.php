<?php

namespace Modules\Access\Database\Seeders;

use App\Core\Support\Permissions;
use App\Core\Support\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the permission catalogue and the default roles.
 *
 * Idempotent: it is safe to re-run after new permissions are added to
 * App\Core\Support\Permissions, which is the intended way to roll them out.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // The registrar caches the permission table; roles created above must
        // be synced against a fresh read or Spatie cannot resolve them.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Roles::all() as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        foreach (Roles::permissionMap() as $roleName => $permissions) {
            Role::findByName($roleName, 'web')->syncPermissions($permissions);
        }

        // Super Admin holds no explicit permissions; Gate::before grants all.
        Role::findByName(Roles::SUPER_ADMIN, 'web')->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
