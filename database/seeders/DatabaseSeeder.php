<?php

namespace Database\Seeders;

use App\Core\Support\Roles;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Access\Database\Seeders\RolePermissionSeeder;
use Modules\Access\Database\Seeders\SuperAdminSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order matters: permissions and roles must exist before any account can
     * be given one.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SuperAdminSeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            User::factory()
                ->create(['name' => 'Test User', 'email' => 'test@example.com'])
                ->assignRole(Roles::ADMIN);
        }
    }
}
