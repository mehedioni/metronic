<?php

namespace Modules\Access;

use App\Core\ModuleServiceProvider;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Access\Console\Commands\MakeSuperAdminCommand;
use Modules\Access\Exceptions\RoleEscalationException;
use Modules\Access\Policies\PermissionPolicy;
use Modules\Access\Policies\RolePolicy;
use Modules\Access\Policies\UserPolicy;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Registers policies for the three models this module governs. They live in
 * app/ and in the Spatie package respectively, so they cannot carry a
 * #[UsePolicy] attribute and are mapped here instead.
 */
class AccessServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Access';
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        $this->registerExceptionRendering();

        if ($this->app->runningInConsole()) {
            $this->commands([MakeSuperAdminCommand::class]);
        }
    }

    /**
     * An escalation attempt is a forbidden action, not a server error.
     */
    private function registerExceptionRendering(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (! $handler instanceof Handler) {
            return;
        }

        $handler->renderable(function (RoleEscalationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'FORBIDDEN', 'message' => $exception->getMessage()],
                ], 403);
            }

            return back()->withErrors(['roles' => $exception->getMessage()]);
        });
    }
}
