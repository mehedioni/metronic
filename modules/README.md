# Modules

Self-contained feature modules live here, under the `Modules\` PSR-4
namespace (see `composer.json`). This directory is empty until the first
module is added — no modules ship by default.

## Adding a module

1. Create `modules/<ModuleName>/` with this layout:

    ```
    modules/<ModuleName>/
    ├── module.json                     # manifest (see below)
    ├── <ModuleName>ServiceProvider.php
    ├── Database/
    │   └── Migrations/
    ├── Http/
    │   └── Controllers/
    ├── Models/
    ├── Services/
    ├── Resources/
    │   └── js/                         # Vue components/pages for this module
    ├── Routes/
    │   ├── web.php                     # Inertia pages, wrapped in "web" middleware
    │   └── api.php                     # JSON endpoints, wrapped in "api" + "api/v1" prefix
    └── Tests/
    ```

2. Add a `module.json` manifest:

    ```json
    {
        "name": "<ModuleName>",
        "slug": "<module-name>",
        "version": "0.1.0",
        "provider": "Modules\\<ModuleName>\\<ModuleName>ServiceProvider"
    }
    ```

3. Write the ServiceProvider extending `App\Core\ModuleServiceProvider`:

    ```php
    namespace Modules\<ModuleName>;

    use App\Core\ModuleServiceProvider;

    class <ModuleName>ServiceProvider extends ModuleServiceProvider
    {
        protected function moduleName(): string
        {
            return '<ModuleName>';
        }
    }
    ```

   `ModuleServiceProvider::boot()` auto-loads `Database/Migrations/`,
   `Routes/web.php`, and `Routes/api.php` for you. Override `boot()` (calling
   `parent::boot()` first) to register views, event listeners, etc.

4. Enable it in `config/modules.php`:

    ```php
    'enabled' => ['<ModuleName>'],
    ```

   `App\Providers\ModulesServiceProvider` discovers enabled modules on every
   request and registers their ServiceProvider — no other wiring needed.

5. If the module has its own Vue pages/components, add a Vite alias in
   `vite.config.ts` (e.g. `@<module>` → `modules/<ModuleName>/Resources/js`)
   and a matching path in `tsconfig.json`.

## Conventions

- Each module owns its own database tables and migrations.
- Cross-module communication goes through Laravel events — never import a
  class from another module directly.
- Models, services, and controllers stay inside the module unless they are
  genuinely shared, in which case they belong in `app/Core/` instead.
