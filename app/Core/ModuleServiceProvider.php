<?php

namespace App\Core;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base ServiceProvider for modules under modules/<Module>. Subclasses only
 * need to declare moduleName(); migrations and routes are auto-loaded from
 * the module's conventional sub-paths.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * The module name (matches the directory name under modules/).
     */
    abstract protected function moduleName(): string;

    public function register(): void
    {
        //
    }

    /**
     * Boot the module: migrations and routes. Override to add views,
     * event listeners, etc., and call parent::boot() first.
     */
    public function boot(): void
    {
        $this->loadMigrations();
        $this->loadRoutes();
    }

    /**
     * Get the module's base path, optionally appending a sub-path.
     */
    protected function modulePath(string $subPath = ''): string
    {
        $base = base_path('modules/'.$this->moduleName());

        return $subPath ? $base.'/'.ltrim($subPath, '/') : $base;
    }

    protected function loadMigrations(): void
    {
        $path = $this->modulePath('Database/Migrations');

        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }

    /**
     * Load the module's web (Inertia) and API routes, if present.
     */
    protected function loadRoutes(): void
    {
        $webRoutes = $this->modulePath('Routes/web.php');

        if (file_exists($webRoutes)) {
            Route::middleware('web')->group($webRoutes);
        }

        $apiRoutes = $this->modulePath('Routes/api.php');

        if (file_exists($apiRoutes)) {
            Route::middleware('api')->prefix('api/v1')->group($apiRoutes);
        }
    }
}
