<?php

namespace App\Providers;

use App\Core\ModuleManager;
use Illuminate\Support\ServiceProvider;

/**
 * Discovers modules listed in config('modules.enabled') and registers each
 * module's ServiceProvider with the container.
 */
class ModulesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class);

        $manager = $this->app->make(ModuleManager::class);
        $manager->discover();

        foreach ($manager->providers() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
