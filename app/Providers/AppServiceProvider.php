<?php

namespace App\Providers;

use App\Core\Support\Roles;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerSuperAdminBypass();
        $this->configurePasswordDefaults();
        $this->configurePasswordResetUrl();
    }

    /**
     * Super Admin implicitly holds every ability, including ones added after
     * the role was seeded. Returning null for everyone else leaves normal
     * policy and permission checks untouched.
     */
    private function registerSuperAdminBypass(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->hasRole(Roles::SUPER_ADMIN) ? true : null;
        });
    }

    private function configurePasswordDefaults(): void
    {
        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }

    /**
     * Point the reset link at the Inertia page rather than the API route.
     */
    private function configurePasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(
            fn (User $user, string $token): string => route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]),
        );
    }
}
