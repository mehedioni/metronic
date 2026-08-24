<?php

namespace App\Core;

use Illuminate\Support\Facades\File;

/**
 * Discovers and registers modules listed in config('modules.enabled').
 *
 * Each module is a self-contained directory under modules/ with a
 * module.json manifest pointing to its ServiceProvider. See
 * modules/README.md for the full scaffold convention.
 */
class ModuleManager
{
    /** @var array<string, array<string, mixed>> */
    protected array $modules = [];

    protected string $modulesPath;

    public function __construct()
    {
        $this->modulesPath = base_path('modules');
    }

    /**
     * Discover and register all enabled modules.
     */
    public function discover(): void
    {
        if (! File::isDirectory($this->modulesPath)) {
            return;
        }

        foreach (config('modules.enabled', []) as $moduleName) {
            $this->register($moduleName);
        }
    }

    /**
     * Register a single module by name.
     */
    public function register(string $name): void
    {
        $manifest = $this->loadManifest($name);

        if (! $manifest) {
            return;
        }

        $this->modules[$name] = $manifest;
    }

    /**
     * Load a module's manifest (module.json).
     *
     * @return array<string, mixed>|null
     */
    protected function loadManifest(string $name): ?array
    {
        $path = $this->modulesPath.'/'.$name.'/module.json';

        if (! File::exists($path)) {
            return null;
        }

        $manifest = json_decode(File::get($path), true);

        if (! $manifest) {
            return null;
        }

        $manifest['path'] = $this->modulesPath.'/'.$name;

        return $manifest;
    }

    /**
     * Get the ServiceProvider FQCN for a module.
     */
    public function getProvider(string $name): ?string
    {
        return $this->modules[$name]['provider'] ?? null;
    }

    /**
     * Get all registered modules, keyed by name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * Check if a module is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    /**
     * Get a module's manifest data.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $name): ?array
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * Get the base path for a module, optionally appending a sub-path.
     */
    public function path(string $name, string $subPath = ''): ?string
    {
        if (! $this->has($name)) {
            return null;
        }

        $base = $this->modules[$name]['path'];

        return $subPath ? $base.'/'.ltrim($subPath, '/') : $base;
    }

    /**
     * Get all ServiceProvider FQCNs for registered modules.
     *
     * @return array<int, string>
     */
    public function providers(): array
    {
        return collect($this->modules)
            ->pluck('provider')
            ->filter()
            ->values()
            ->all();
    }
}
