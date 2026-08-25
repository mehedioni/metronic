<?php

namespace Modules\Inventory;

use App\Core\ModuleServiceProvider;
use App\Core\Support\Permissions;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Inventory\Exceptions\InventoryException;

/**
 * Models declare their own policies with #[UsePolicy], so this provider only
 * has to register the module's exception rendering on top of the migrations
 * and routes handled by the parent.
 */
class InventoryServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Inventory';
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerAbilities();
        $this->registerExceptionRendering();
    }

    /**
     * Abilities that guard a read rather than a record.
     *
     * The report is an aggregate over orders, order lines and expenses, so
     * there is no model to hang a policy on — but the controller still has to
     * re-check what the route middleware asserted, the way every other screen
     * in this module does.
     */
    private function registerAbilities(): void
    {
        Gate::define(
            'viewReports',
            fn (User $user): bool => $user->can(Permissions::REPORTS_VIEW),
        );
    }

    /**
     * Domain rule violations (insufficient stock, illegal status change,
     * restricted deletion) are user-correctable, so they surface as a 422 for
     * the API and as a field error for Inertia — never as a 500.
     */
    private function registerExceptionRendering(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (! $handler instanceof Handler) {
            return;
        }

        $handler->renderable(function (InventoryException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DOMAIN_RULE_VIOLATION',
                        'message' => $exception->getMessage(),
                    ],
                ], 422);
            }

            return back()->withErrors([$exception->errorKey() => $exception->getMessage()]);
        });
    }
}
