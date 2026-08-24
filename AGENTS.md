<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>

# RentMy Admin — Architecture

> The block above is auto-managed by `php artisan boost:update` (regenerated on
> `composer update`) — don't hand-edit inside `<laravel-boost-guidelines>`.
> Everything below is hand-maintained and safe to edit.

## Project Overview

- **Name:** RentMy Admin — admin panel for RentMy, built as an API + Inertia
  SPA in a single repo.
- **Stack:** Laravel 13 (PHP 8.4), Vue 3 (Composition API + `<script setup>`),
  Inertia v3, Tailwind CSS 4, TypeScript.
- **Architecture:** Modular monolith. Shared building blocks live in
  `app/Core/`; self-contained features live in `modules/`. No feature modules
  exist yet — this is scaffolding only, added ahead of real feature work.
- **Reference:** This architecture (module system, `Core` layer, JSON API
  response shape, frontend tooling) mirrors `heldsway_api`, a sibling Laravel
  + Vue + Inertia project, so both codebases stay easy to move between. Not
  copied: heldsway's multi-tenant domain-based routing (`APP_DOMAIN`/
  `API_DOMAIN` subdomain resolution) — rentmy_admin has no multi-tenancy
  requirement yet. If one emerges, look at heldsway's `ResolveBusinessDomain`
  middleware and `config/domains.php` pattern before inventing a new one.

## Architecture Map

### Core App Layer (`app/Core/`)

Shared base classes reused by both `app/` and every module:

- `BaseModel` — abstract Eloquent base with UUID primary keys (`HasUuids`)
  and `SoftDeletes`. Use this for new domain models. `App\Models\User` is the
  exception: it follows Laravel's default auth conventions (auto-increment
  int id) since Fortify/Sanctum-style auth packages assume that.
- `BaseApiController` — abstract controller for JSON endpoints; pulls in the
  `ApiResponse` trait. Inertia page controllers extend the default
  `App\Http\Controllers\Controller` instead.
- `Traits\ApiResponse` — consistent JSON envelope: `success()`, `created()`,
  `error()`, `notFound()`, `forbidden()`, `unauthorized()`, `validationError()`.
- `ModuleManager` / `ModuleServiceProvider` — see Modules below.

### Modules (`modules/`)

Pluggable feature modules under the `Modules\` PSR-4 namespace (see
`composer.json`). Empty today — see `modules/README.md` for the full scaffold
convention (directory layout, `module.json` manifest, ServiceProvider
skeleton) before adding the first one.

- Enabled modules are listed in `config/modules.php` (`'enabled' => []`
  currently). `App\Providers\ModulesServiceProvider` discovers and registers
  each module's ServiceProvider on every request boot.
- Each module extends `App\Core\ModuleServiceProvider`, which auto-loads
  `Database/Migrations/`, `Routes/web.php` (wrapped in `web` middleware), and
  `Routes/api.php` (wrapped in `api` middleware, `api/v1` prefix).
- Each module owns its own tables. Cross-module communication is via Laravel
  events only — never import a class from another module directly.
- A module with its own Vue pages/components gets a Vite alias (e.g.
  `@example` → `modules/Example/Resources/js`) added to `vite.config.ts`, and
  a matching path in `tsconfig.json`.

### Routing

- `routes/web.php` — Inertia pages, `web` middleware (session auth, CSRF).
- `routes/api.php` — JSON API, auto-prefixed `api/` + `api` middleware group
  (registered via `api:` in `bootstrap/app.php`). Version with a `Route::
  prefix('v1')` group as endpoints are added.
- No domain-based routing — everything above serves the single app domain.
  API responses are forced to JSON via `shouldRenderJsonWhen` in
  `bootstrap/app.php` for any `api/*` path.

### Frontend

- Entry: `resources/js/app.ts`. SSR entry: `resources/js/ssr.ts` (wired via
  `@inertiajs/vue3/server` + `vite build --ssr`), but **disabled by default**
  (`INERTIA_SSR_ENABLED=false` in `.env`) until `php artisan inertia:start-ssr`
  is actually run somewhere.
- Pages in `resources/js/pages/`, layouts in `resources/js/layouts/`, shared
  UI in `resources/js/components/ui/` (shadcn-vue style — `Button` is the
  first example; add more with the `shadcn-vue` CLI, guided by
  `components.json`).
- `resources/js/lib/utils.ts` exports `cn()` (clsx + tailwind-merge) for
  conditional class composition — use it instead of manual string
  concatenation in any component with variant classes.
- `resources/js/composables/useAppearance.ts` — light/dark/system theme,
  applied via a `.dark` class on `<html>`; boot script in
  `resources/views/app.blade.php` prevents a flash of the wrong theme.
- `resources/js/types/index.d.ts` — `SharedData` types the props every page
  gets from `HandleInertiaRequests::share()` (currently just `auth.user`).
  Extend this interface whenever a new key is added to `share()`.
- UI stack: Vue 3 Composition API (`<script setup>`) + TypeScript, `reka-ui`,
  shadcn-vue components, Tailwind CSS 4, `lucide-vue-next` icons.
  `@laravel/vite-plugin-wayfinder` generates typed route/controller helpers
  (see the `wayfinder-development` skill) — import from `@/actions` or
  `@/routes`, never hardcode a URL string.

### Inertia v3

- This project uses **Inertia v3** — note that `heldsway_api` (the
  architecture reference above) is still on **v2**. Don't copy v2-specific
  patterns (e.g. manual `resolvePageComponent` + `laravel-vite-plugin`
  toasts/flash wiring) from that repo without checking they still apply; and
  don't apply v2→v3 migration guides here since this project was never on v2.
- Root view uses the `<x-inertia::head />` / `<x-inertia::app />` Blade
  components (the v3-recommended form) rather than the older
  `@inertiaHead` / `@inertia` directives.

## Security Conventions

- **`$fillable` on every model** — explicit mass-assignment allow-list, no
  exceptions.
- **State changes are POST/PUT/PATCH/DELETE, never GET** — a mutating GET is
  CSRF/prefetch-triggerable.
- **`env()` only inside `config/` files** (so `config:cache` works). Read
  from `config()` everywhere else.
- **No business logic in controllers** — delegate to a service (in
  `app/Core/Services/` or `modules/<Module>/Services/` once one exists) and
  return a response (Inertia render or JSON via `ApiResponse`).
- **Validation**: Form Request classes consuming `$request->validated()`.
  Never validate inline in the controller.
- **No N+1** — eager-load with `with()`; paginate rather than load-all.
- If/when multi-tenancy is introduced, scope every tenant-owned query by the
  resolved tenant id — never trust a client-supplied tenant id. Follow
  heldsway_api's `business_id` scoping pattern rather than inventing a new one.

## Commands

### Dev servers
```bash
composer dev          # server + queue + pail logs + Vite, all at once
npm run dev           # Vite only
npm run build         # production build
npm run build:ssr     # SSR build (client + server bundles)
```

### Lint / Format / Types (frontend)
```bash
npm run lint          # ESLint with autofix
npm run lint:check    # ESLint check only
npm run format        # Prettier write
npm run format:check  # Prettier check
npm run types:check   # vue-tsc --noEmit
```

### Lint (PHP)
```bash
vendor/bin/pint --dirty --format agent   # see pint/core rules above
```

### Testing
```bash
php artisan test --compact
php artisan test --compact --filter="test name"
```

### Wayfinder
```bash
php artisan wayfinder:generate --with-form --no-interaction
```
`vite.config.ts` runs the Wayfinder Vite plugin automatically on `npm run
dev` / `npm run build`, so manual generation is normally only needed if the
plugin isn't running. Generated files under `resources/js/{actions,routes,
wayfinder}` are gitignored — don't commit them.

## Conventions

- **SOLID**: one reason to change per class; controllers do HTTP only; one
  focused service per domain concern once real logic exists (skip a service
  for a single-line Eloquent call); depend on abstractions via constructor
  injection; extend behavior via events/middleware/config rather than
  reaching into another module's internals.
- **DRY**: check for an existing helper, trait, service, or base class (`app/
  Core/`) before writing new logic.
- **Imports**: always import classes at the top — no inline FQCNs.
- **Comments**: only to explain *why*, never to restate what the code says.
- Everything else (formatting, testing conventions, Artisan usage) follows
  the Laravel Boost guidelines block above — those stay current automatically
  as packages change, this section documents what Boost can't infer.
