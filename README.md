# RentMy Admin

Admin panel for RentMy. Laravel 13 API and Vue 3 + Inertia SPA in a single
repo, built as a modular monolith.

**A product of [Leaping Logic LLC](https://leapinglogic.com/)**

---

## Table of Contents

- [Tech Stack](#tech-stack)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Quick Start](#quick-start)
- [Environment Configuration](#environment-configuration)
- [Module System](#module-system)
- [Development Commands](#development-commands)
- [Testing](#testing)
- [Project Structure](#project-structure)

---

## Tech Stack

| Layer        | Technology                                          |
|--------------|-----------------------------------------------------|
| Backend      | PHP 8.4, Laravel 13                                 |
| Frontend     | Vue 3 (Composition API), TypeScript, Inertia.js 3   |
| UI           | Tailwind CSS 4, shadcn-vue, Lucide Icons            |
| Database     | SQLite (local dev) — swap via `DB_CONNECTION`       |
| Build        | Vite                                                |
| Testing      | Pest PHP 4                                          |
| Linting      | Laravel Pint (PHP), ESLint + Prettier (JS/Vue)      |

---

## Architecture

This is a **modular monolith**: API and frontend live in one repo, shared
building blocks live in `app/Core/`, and self-contained features are added
under `modules/` as they're built (none exist yet — this repo currently
ships only the scaffolding).

- **`app/Core/`** — `BaseModel` (UUID + soft deletes), `BaseApiController` +
  `ApiResponse` trait (consistent JSON envelope), `ModuleManager` /
  `ModuleServiceProvider` (module discovery and boot).
- **`modules/`** — pluggable feature modules, enabled via
  `config/modules.php` and auto-registered by
  `App\Providers\ModulesServiceProvider`. See [modules/README.md](modules/README.md)
  for the scaffold convention (directory layout, manifest, ServiceProvider).
- **Routing** — `routes/web.php` serves Inertia pages (`web` middleware);
  `routes/api.php` serves JSON (`api` middleware, `api/` prefix). No
  multi-tenant/domain-based routing at this stage.
- **Frontend** — Vue 3 `<script setup>` + TypeScript, Inertia v3, Tailwind
  CSS 4, `reka-ui` + shadcn-vue components, `lucide-vue-next` icons, and
  Laravel Wayfinder for typed route/controller helpers (`@/actions`,
  `@/routes`).

This structure mirrors a sibling project (`heldsway_api`) so the two
codebases stay easy to move between, minus heldsway's multi-tenant
domain-routing layer, which doesn't apply here yet. Full conventions and
rationale live in [AGENTS.md](AGENTS.md).

---

## Requirements

| Software | Version |
|----------|---------|
| PHP      | >= 8.3  |
| Composer | >= 2.x  |
| Node.js  | >= 20.x |
| npm      | >= 10.x |

---

## Quick Start

```bash
# 1. Clone the repository
git clone <repo-url> rentmy_admin
cd rentmy_admin

# 2. Install dependencies
composer install
npm install

# 3. Environment setup
cp .env.example .env
php artisan key:generate

# 4. Database setup
php artisan migrate

# 5. Build frontend
npm run build

# 6. Start development
composer dev
```

The app is served locally via [Laravel Herd](https://herd.laravel.com) at
`http://rentmy_admin.test` (or whatever host your local setup resolves).

---

## Environment Configuration

Copy `.env.example` to `.env` and configure as needed. Key variables:

```dotenv
APP_NAME="RentMy Admin"
APP_URL=http://rentmy_admin.test

DB_CONNECTION=sqlite   # swap to mysql/pgsql + host/port/database/credentials

INERTIA_SSR_ENABLED=false
```

- **Database**: defaults to SQLite for local dev. Switch `DB_CONNECTION` and
  add the matching `DB_*` variables for MySQL/PostgreSQL.
- **SSR**: the SSR entry (`resources/js/ssr.ts`) and build (`npm run
  build:ssr`) are wired up but disabled by default. Enable
  `INERTIA_SSR_ENABLED=true` only once `php artisan inertia:start-ssr` is
  actually running somewhere.
- Never hardcode config values that vary per environment — read them via
  `config()`, which reads `env()` only inside `config/*.php` files.

---

## Module System

Feature modules live in `modules/` under the `Modules\` PSR-4 namespace.
None are enabled yet (`config('modules.enabled')` is empty) — this repo only
ships the mechanism:

```
modules/<ModuleName>/
├── module.json                     # manifest: name, slug, version, provider
├── <ModuleName>ServiceProvider.php # extends App\Core\ModuleServiceProvider
├── Database/Migrations/
├── Http/Controllers/
├── Models/
├── Services/
├── Resources/js/                   # Vue pages/components for this module
├── Routes/
│   ├── web.php                     # Inertia pages
│   └── api.php                     # JSON endpoints (api/v1 prefix)
└── Tests/
```

See [modules/README.md](modules/README.md) for the full walkthrough of adding
a new module. Each module owns its own tables; cross-module communication is
via Laravel events only.

---

## Development Commands

### Dev servers
```bash
composer dev          # server + queue + pail logs + Vite, all at once
npm run dev            # Vite only
npm run build           # production frontend build
npm run build:ssr       # SSR build (client + server bundles)
```

### Linting, formatting, types
```bash
vendor/bin/pint --dirty --format agent   # PHP (Laravel preset)
npm run lint                             # ESLint (autofix)
npm run format                           # Prettier (write)
npm run types:check                      # vue-tsc --noEmit
```

### Wayfinder (typed routes)
```bash
php artisan wayfinder:generate --with-form --no-interaction
```
Runs automatically via the Vite plugin during `npm run dev`/`build`.
Generated files under `resources/js/{actions,routes,wayfinder}` are
gitignored — don't commit them.

---

## Testing

```bash
php artisan test --compact
php artisan test --compact --filter="test name"
```

Tests use **Pest PHP** against the local SQLite database configured in
`phpunit.xml`.

---

## Project Structure

```
├── app/
│   ├── Core/                       # Shared base classes (see Architecture)
│   │   ├── BaseModel.php
│   │   ├── BaseApiController.php
│   │   ├── ModuleManager.php
│   │   ├── ModuleServiceProvider.php
│   │   └── Traits/ApiResponse.php
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Middleware/HandleInertiaRequests.php
│   ├── Models/                     # User (default Laravel auth conventions)
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── ModulesServiceProvider.php  # discovers/boots modules
├── config/
│   └── modules.php                 # enabled modules list
├── modules/                        # feature modules (none yet)
│   └── README.md                   # scaffold convention
├── resources/
│   ├── css/app.css
│   ├── js/
│   │   ├── app.ts                  # Inertia entry
│   │   ├── ssr.ts                  # SSR entry (wired, disabled by default)
│   │   ├── components/ui/          # shadcn-vue components
│   │   ├── composables/            # useAppearance, etc.
│   │   ├── layouts/
│   │   ├── lib/utils.ts            # cn() helper
│   │   ├── pages/                  # Inertia pages
│   │   └── types/                  # SharedData + shared TS types
│   └── views/app.blade.php         # Inertia root view
├── routes/
│   ├── web.php                     # Inertia pages
│   ├── api.php                     # JSON API
│   └── console.php
└── vite.config.ts
```

---

## License

Proprietary. All rights reserved.

Copyright (c) 2026 [Leaping Logic LLC](https://leapinglogic.com/). All rights reserved.
