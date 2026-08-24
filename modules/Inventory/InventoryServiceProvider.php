<?php

namespace Modules\Inventory;

use App\Core\ModuleServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
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

        $this->registerExceptionRendering();
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
