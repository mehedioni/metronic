<?php

namespace Modules\Access\Console\Commands;

use App\Core\Support\Roles;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Recovery path for granting Super Admin to an existing account without
 * touching the database by hand.
 */
class MakeSuperAdminCommand extends Command
{
    protected $signature = 'access:super-admin {email : Email address of an existing user}';

    protected $description = 'Grant the Super Admin role to an existing user';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error("No user found for {$this->argument('email')}.");

            return self::FAILURE;
        }

        $user->assignRole(Roles::SUPER_ADMIN);

        $this->components->info("{$user->email} is now a Super Admin.");

        return self::SUCCESS;
    }
}
