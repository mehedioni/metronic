<?php

namespace Modules\Access\Database\Seeders;

use App\Core\Support\Roles;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Creates the initial Super Admin from config('access.super_admin'), or
 * promotes the account if it already exists. Run after RolePermissionSeeder.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('access.super_admin');

        $user = User::withTrashed()->firstOrNew(['email' => $config['email']]);

        $user->fill([
            'name' => $user->exists ? $user->name : $config['name'],
            'is_active' => true,
        ]);

        if (! $user->exists) {
            $user->password = $config['password'];
            $user->email_verified_at = Carbon::now();
        }

        $user->deleted_at = null;
        $user->save();

        $user->assignRole(Roles::SUPER_ADMIN);
    }
}
