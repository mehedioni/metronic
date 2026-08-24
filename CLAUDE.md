# Backend-First Implementation of Existing Laravel + Vue + Inertia Project

You are a senior Laravel, Vue 3, Inertia.js, database architecture, authentication, authorization, and inventory-management engineer.

You are working inside an **existing application**. Your task is to carefully inspect the current codebase and extend it into a fully functional, backend-ready inventory/store management system.

The UI is **not the primary focus right now**. I will provide the final UI designs later.

Your responsibility is to build the application's foundation so that the future UI can connect to a clean, secure, scalable, and production-ready backend without requiring major changes to the business logic or database architecture.

## Reference / Functional Inspiration

Use this Metronic Store Inventory concept as functional inspiration:

https://keenthemes.com/metronic/concepts/vite/store-inventory/

Use the reference to understand the expected modules and workflows.

Do **not** blindly copy the frontend implementation.

The goal is to build the functionality, database structure, backend architecture, authentication, authorization, validation, and business rules first.

---

# Phase 1: Inspect the Existing Project

Before making changes:

1. Inspect the complete project structure.
2. Identify:

   * Laravel version
   * PHP version
   * Vue version
   * Inertia version
   * Existing authentication implementation
   * Existing models
   * Existing migrations
   * Existing routes
   * Existing controllers
   * Existing Vue pages/components
   * Existing API endpoints
   * Existing middleware
   * Existing roles or permissions
   * Existing database relationships
   * Existing coding conventions
3. Check `composer.json` and `package.json`.
4. Do not replace existing architecture unless there is a clear technical reason.
5. Reuse existing patterns whenever possible.

Before implementing anything, provide me with a concise analysis containing:

* Current architecture
* Existing modules
* Existing authentication status
* Existing authorization status
* Missing backend pieces
* Potential conflicts
* Recommended implementation plan

Then proceed with the implementation unless you discover a critical ambiguity that makes implementation impossible.

Do not stop and ask unnecessary questions.

Make sensible architectural decisions based on the existing project.

---

# Core Goal

Transform the existing project into a backend-ready inventory management system with:

* Proper authentication
* User management
* Role and permission management using Spatie
* Products
* Categories
* Suppliers
* Product variants
* Inventory tracking
* Stock movements
* Inbound inventory
* Outbound inventory
* Purchase/receiving workflow where appropriate
* Orders
* Shipments
* Dashboard statistics
* Search
* Filtering
* Pagination
* Validation
* Error handling
* Database integrity
* Audit-friendly inventory history

The system should be designed so the frontend UI can be redesigned later without rewriting the backend.

---

# Phase 2: Authentication

First inspect whether authentication already exists.

If authentication is incomplete or missing, implement authentication using the most appropriate solution for the current Laravel and Inertia architecture.

Authentication should support:

* Login
* Logout
* Registration only if appropriate for the project
* Password reset
* Email verification if appropriate
* Remember me
* Session management
* Protected routes
* Guest routes
* Authenticated user sharing with Inertia

Use Laravel's standard authentication patterns and integrate them cleanly with the existing application.

Do not build a separate or disconnected authentication architecture if the project already uses Laravel authentication.

The frontend authentication pages can remain minimal for now.

The important part is that authentication must be fully functional.

---

# Phase 3: Role and Permission Management Using Spatie

Use **Spatie Laravel Permission** for all role and permission management.

Before installation, inspect the project to determine whether:

* Spatie Laravel Permission is already installed.
* Roles and permissions already exist.
* Existing authorization logic needs to be migrated or integrated.

If the package is not already installed, install and configure it correctly.

Use the official package architecture and Laravel conventions.

Do not create a custom roles/permissions system when Spatie Laravel Permission is being used.

Configure the User model appropriately, including the required traits and relationships.

Create the necessary migrations and configuration.

The system should support roles such as:

* Super Admin
* Admin
* Manager
* Inventory Manager
* Staff

Design permissions granularly.

Example permissions may include:

## Dashboard

* dashboard.view

## Products

* products.view
* products.create
* products.update
* products.delete

## Categories

* categories.view
* categories.create
* categories.update
* categories.delete

## Suppliers

* suppliers.view
* suppliers.create
* suppliers.update
* suppliers.delete

## Inventory

* inventory.view
* inventory.create
* inventory.adjust
* inventory.delete

## Orders

* orders.view
* orders.create
* orders.update
* orders.cancel

## Shipments

* shipments.view
* shipments.create
* shipments.update
* shipments.delete

## Users

* users.view
* users.create
* users.update
* users.delete

## Roles and Permissions

* roles.view

* roles.create

* roles.update

* roles.delete

* permissions.view

* permissions.manage

Use consistent permission naming conventions throughout the application.

Do not hardcode role names throughout controllers.

Prefer permission checks for individual capabilities.

Use roles for grouping permissions.

Protect functionality at multiple levels where appropriate:

* Route middleware
* Controller authorization
* Policies
* Service-level validation for critical operations

Unauthorized users must not be able to access or manipulate protected functionality by manually calling application routes.

Seed default roles and permissions.

Also create a clear strategy for assigning the initial Super Admin role.

Do not expose role or permission management to users who do not have the appropriate permission.

---

# Phase 4: User Management

Implement or complete backend functionality for user management.

Support:

* User listing
* Search
* Pagination
* Create user
* Update user
* Activate/deactivate user if appropriate
* Assign roles
* Assign permissions where appropriate
* Prevent unauthorized role escalation
* Password management

When updating users, ensure that users cannot assign themselves or others to roles/permissions beyond their authorization level.

Use Spatie relationships and APIs correctly.

Do not manually manipulate the Spatie database tables unless necessary.

---

# Phase 5: Database Architecture

Review the existing database before creating new tables.

Avoid duplicate tables or duplicate concepts.

Create or update migrations as necessary.

The architecture should support at minimum:

* Users
* Roles and permissions through Spatie
* Categories
* Suppliers
* Products
* Product variants
* Inventory records
* Stock movements
* Purchase or inbound inventory records where appropriate
* Orders
* Order items
* Shipments

Use proper:

* Foreign keys
* Indexes
* Unique constraints
* Timestamps
* Soft deletes where appropriate

---

# Phase 6: Categories

Support:

* ID
* Name
* Slug
* Description
* Parent category if hierarchical categories are useful
* Status
* Timestamps

Requirements:

* Categories should support relationships with products.
* Slugs should be unique where appropriate.
* Prevent invalid circular parent relationships.
* Prevent deletion when doing so would corrupt existing product relationships or historical data.

---

# Phase 7: Suppliers

Implement a complete **Suppliers Management** module.

Suppliers represent companies or individuals from whom products or inventory are purchased or received.

The supplier architecture must be reusable and scalable.

## Supplier Fields

Support fields such as:

* ID
* Company name
* Supplier code or reference number
* Contact person name
* Email
* Phone
* Alternative phone if useful
* Website
* Address
* City
* State/region
* Postal/ZIP code
* Country
* Tax or registration number where appropriate
* Payment terms if useful
* Notes
* Status
* Timestamps
* Soft deletes where appropriate

Do not add unnecessary fields if equivalent structures already exist in the project.

## Supplier Functionality

Implement:

* Supplier list
* Search
* Filtering
* Pagination
* Create supplier
* View supplier
* Update supplier
* Activate/deactivate supplier
* Safe deletion or archival

Search should support relevant fields such as:

* Company name
* Supplier code
* Contact person
* Email
* Phone

## Supplier Relationships

Suppliers should be architected to support relationships with:

* Products
* Product variants where appropriate
* Purchase/inbound inventory
* Future purchase orders

Think carefully about the relationship.

A product may:

* Have one primary supplier, or
* Have multiple suppliers.

Design the relationship so the system can support future expansion without major restructuring.

If multiple suppliers per product are appropriate, consider a dedicated relationship/pivot table that can later support fields such as:

* Supplier product SKU
* Supplier-specific cost
* Minimum order quantity
* Lead time
* Preferred supplier status

Do not overcomplicate the implementation unnecessarily, but avoid an architecture that will need to be replaced when multiple suppliers are introduced.

## Supplier History

Prepare supplier data so future screens can show:

* Products supplied
* Inventory received from the supplier
* Purchase history
* Total received quantity
* Most recent receiving activity

Historical inbound inventory must remain valid even if supplier information is later updated or archived.

---

# Phase 8: Products

Design products so they can support both simple and variant-based products.

Suggested fields:

* ID
* Name
* Slug
* SKU where appropriate
* Description
* Category
* Status
* Primary supplier where appropriate
* Image/media references if the project already supports uploads
* Additional metadata where useful
* Timestamps

Requirements:

* Products may have variants.
* A simple product may operate without variants.
* Products may have one or multiple suppliers depending on the selected architecture.
* Supplier relationships should not destroy historical purchase or inventory records.
* Product inventory should not be managed using only an unreliable manually edited number when stock movement history is required.

---

# Phase 9: Product Variants

Support:

* ID
* Product ID
* SKU
* Variant name
* Option values
* Cost fields where appropriate
* Price fields if needed later
* Status
* Timestamps

Examples:

* Size
* Color
* Weight
* Material

The database structure should allow future UI flexibility.

Avoid creating a schema that only works for one hardcoded variant type.

Ensure SKU uniqueness where appropriate.

---

# Phase 10: Inventory Architecture

This is one of the most important parts of the project.

Do not implement inventory as only:

`products.stock = products.stock + 1`

Instead, create an inventory architecture that supports:

* Stock history
* Traceability
* Supplier receiving history
* Order fulfillment history
* Auditability

Implement an inventory or stock movement system.

Possible movement types include:

* Opening stock
* Stock received
* Purchase/inbound
* Supplier delivery
* Stock adjustment increase
* Stock adjustment decrease
* Order allocation
* Order fulfillment
* Shipment outbound
* Return
* Damage
* Transfer if applicable

Each inventory movement should capture relevant information such as:

* ID
* Product or product variant
* Supplier where applicable
* Movement type
* Quantity
* Quantity before
* Quantity after
* Reference type
* Reference ID
* Reason or note
* User responsible
* Timestamp

Use database transactions for inventory operations.

Prevent race conditions and inconsistent stock.

Where appropriate:

* Use database locking.
* Validate available stock before decreasing inventory.
* Prevent negative stock unless the business rules explicitly allow it.

Do not allow stock quantities to become inconsistent when multiple requests modify the same inventory simultaneously.

---

# Phase 11: Inbound Inventory / Receiving

Implement a structured inbound inventory workflow.

Inbound inventory may come from:

* Suppliers
* Purchases
* Manual receiving
* Customer returns
* Opening stock
* Inventory transfers if applicable

Supplier-based receiving should support:

* Supplier
* Reference number
* Receiving date
* Received by user
* Notes
* Status where appropriate
* Multiple received items

Each inbound item should support:

* Product
* Product variant where applicable
* Quantity
* Unit cost where appropriate
* Supplier SKU where appropriate
* Notes

Receiving inventory must:

1. Validate the supplier relationship where applicable.
2. Validate the products/variants.
3. Use a database transaction.
4. Create the inbound record.
5. Create associated inventory movement records.
6. Update the available inventory correctly.

Do not create duplicate stock movements if a receiving record is updated or processed multiple times.

Use clear statuses such as:

* Draft
* Pending
* Received
* Cancelled

Only the appropriate status transition should affect inventory.

---

# Phase 12: Outbound Inventory

Support inventory leaving the system.

Possible sources:

* Orders
* Shipments
* Manual removal
* Damage
* Transfers

Every stock change must have traceable history.

Outbound inventory should validate stock availability before final processing.

Use transactions.

Prevent duplicate deductions.

---

# Phase 13: Orders

Implement a backend-ready order system.

The UI can remain minimal.

Orders should support:

* Order number
* Customer information if applicable
* Order items
* Product
* Variant
* Quantity
* Status
* Inventory impact
* Notes
* Timestamps

Suggested statuses:

* Draft
* Pending
* Confirmed
* Processing
* Shipped
* Completed
* Cancelled

Clearly define the inventory event responsible for stock changes.

For example:

* Reserve stock when confirmed if reservation is implemented.
* Deduct stock when fulfilled or shipped.
* Restore stock when cancelled if inventory was previously deducted.

Do not automatically deduct stock in a way that can cause duplicate deductions.

Use transactions.

Prevent overselling unless explicitly supported.

---

# Phase 14: Shipments

Prepare shipment functionality.

Support fields such as:

* Shipment number
* Order relationship
* Status
* Carrier
* Tracking number
* Shipment date
* Delivery date
* Notes

Suggested statuses:

* Pending
* Preparing
* Shipped
* In Transit
* Delivered
* Cancelled

The shipment system should be designed so external shipping provider integrations can be added later.

Do not tightly couple the database architecture to one carrier.

---

# Phase 15: Dashboard

Create backend services or queries for dashboard data.

The frontend design is not important yet.

Prepare clean data for:

* Total products
* Total categories
* Total suppliers
* Active suppliers
* Total inventory
* Low-stock products
* Recent stock movements
* Recent inbound inventory
* Recent orders
* Orders by status
* Inventory movement summary
* Inbound stock summary
* Outbound stock summary

Use efficient queries.

Do not load unnecessary records into memory.

---

# Phase 16: Laravel Architecture

Keep the codebase clean.

Use appropriate separation of concerns.

Prefer a structure similar to:

* Models
* Controllers
* Form Requests
* Services or Actions for complex business logic
* Policies
* Spatie middleware and permission checks
* Resources or Transformers if appropriate
* Events and listeners where appropriate
* Jobs for expensive or asynchronous processes
* Enums for stable status and movement types where supported by the current project

Do not put complex inventory, supplier receiving, or order logic directly inside controllers.

Controllers should remain thin.

Example responsibility:

Controller
→ validates request
→ authorizes action
→ calls service/action
→ returns Inertia response or redirect

Business logic should live in dedicated services/actions.

---

# Phase 17: Validation

Create dedicated Form Request classes where appropriate.

Validate:

* Required fields
* Unique fields
* Foreign keys
* Supplier information
* Supplier status
* Product/supplier relationships
* Quantity values
* Stock availability
* Valid status transitions
* Variant uniqueness
* Category relationships

Return validation errors in a format compatible with the existing Inertia application.

---

# Phase 18: Routes and Inertia Integration

This is an Inertia application.

Keep the backend compatible with Inertia.

Prepare appropriate routes and controllers for:

* Dashboard
* Products
* Product variants
* Categories
* Suppliers
* Inventory
* Stock movements
* Inbound receiving
* Outbound inventory
* Orders
* Shipments
* Users
* Roles
* Permissions

Use route names consistently.

Do not create duplicate routes.

Use route model binding where appropriate.

Protect routes using Spatie permissions and/or Laravel policies as appropriate.

Respect the existing routing conventions.

---

# Phase 19: Frontend Preparation

The final UI will be provided later.

Therefore:

* Do not spend significant time designing new UI.
* Do not replace the existing frontend architecture unnecessarily.
* Do not introduce a completely different frontend framework.
* Preserve Vue 3 and Inertia.
* Keep existing working pages functional.

However, create the necessary Inertia pages or placeholders if needed for functionality.

For now, pages may be simple but should support:

* Listing data
* Basic create/edit forms where necessary
* Validation errors
* Pagination
* Search
* Filtering
* Authorization-aware actions

The backend should be easy to connect to a future Metronic-inspired UI.

When the final UI is provided later, I should primarily need to update:

* Vue templates
* Components
* Layouts
* Styling

I should not need to redesign:

* Database schema
* Authentication
* Spatie roles and permissions
* Authorization logic
* Controllers
* Services
* Supplier management
* Inventory logic
* Order logic

---

# Phase 20: Search, Filtering, and Pagination

Implement reusable query filtering where appropriate.

Major lists should support:

## Products

* Search by name
* Search by SKU
* Filter by category
* Filter by supplier
* Filter by status
* Low stock

## Suppliers

* Search by company name
* Search by supplier code
* Search by contact person
* Search by email
* Search by phone
* Filter by status

## Orders

* Search by order number
* Filter by status
* Filter by date

## Inventory

* Filter by movement type
* Supplier
* Product
* Variant
* Date range
* User

Preserve query parameters through pagination where appropriate.

---

# Phase 21: Data Integrity

Protect the database.

Use:

* Foreign key constraints
* Unique indexes
* Appropriate indexes
* Transactions
* Database locking where necessary
* Soft deletes where appropriate

Think carefully about deleting:

* Products with inventory history
* Suppliers with receiving history
* Categories with products
* Orders with shipments
* Records referenced by stock movements

Do not allow destructive actions that corrupt historical inventory records.

Prefer archival, soft deletion, or status changes when historical data must remain valid.

Supplier historical relationships must remain valid even if a supplier is deactivated or archived.

---

# Phase 22: Testing

Add meaningful tests for critical functionality.

At minimum, test:

## Authentication

* Guest cannot access protected routes.
* Authenticated user can access authorized routes.

## Spatie Authorization

* Users with required permissions can access protected functionality.
* Users without permissions are denied.
* Role permissions work correctly.
* Users cannot perform unauthorized role or permission escalation.

## Suppliers

* Supplier creation.
* Supplier validation.
* Supplier search/filtering.
* Supplier relationships.
* Supplier archival or deletion rules.

## Products

* Product creation.
* Product validation.
* Product/supplier relationship.
* Variant creation.

## Inventory

* Stock can increase correctly.
* Supplier receiving creates inventory correctly.
* Stock can decrease correctly.
* Stock cannot become negative when negative inventory is disabled.
* Inventory movement history is created.
* Inventory updates occur inside the expected transaction flow.
* Duplicate receiving or processing does not create duplicate inventory changes.

## Orders

* Orders create correctly.
* Order inventory behavior is correct.
* Cancellation/restoration logic works correctly if implemented.

Do not create meaningless tests simply to increase test count.

Focus on business-critical workflows.

---

# Phase 23: Documentation

After implementation, create or update documentation inside the project.

Document:

## Architecture

Explain:

* Models
* Relationships
* Services/actions
* Inventory flow
* Supplier flow
* Receiving flow
* Order flow

## Authentication

Explain:

* Authentication mechanism
* Middleware
* Protected routes

## Authorization

Explain:

* Spatie Laravel Permission implementation
* Roles
* Permissions
* Permission middleware
* Policies
* Initial Super Admin setup

## Suppliers

Explain:

* Supplier structure
* Product/supplier relationships
* Receiving inventory from suppliers
* Supplier history

## Inventory

Explain exactly:

* How stock is calculated
* What creates a stock movement
* How stock is increased
* How stock is decreased
* How supplier receiving affects stock
* How cancellations or returns affect stock

## Database

Document the important tables and relationships.

## Future Frontend Integration

Explain which:

* Inertia routes
* Controllers
* Actions
* Props
* Backend data structures

should be used when implementing the final UI.

---

# Critical Development Rules

Follow these rules throughout the implementation:

1. Do not blindly overwrite existing files.
2. Inspect before modifying.
3. Preserve existing working functionality.
4. Reuse existing architecture where reasonable.
5. Use **Spatie Laravel Permission** for roles and permissions.
6. Do not create a custom duplicate role/permission system.
7. Do not introduce unnecessary dependencies.
8. Do not create duplicate models, migrations, or tables.
9. Do not hardcode business rules inside Vue components.
10. Do not put complex business logic inside controllers.
11. Do not implement inventory changes without transaction safety.
12. Do not silently ignore errors.
13. Do not fake functionality with static frontend data.
14. Do not create UI-only features that have no backend implementation.
15. Do not break existing authentication.
16. Do not remove existing functionality unless required and documented.
17. Use clear naming conventions consistent with the existing project.
18. Prefer maintainable and scalable solutions over quick hacks.
19. Run existing tests and relevant new tests.
20. Run configured code formatting and linting tools.
21. Fix errors caused by your changes.
22. Before considering the task complete, inspect all modified files for consistency.
23. Ensure supplier and inventory operations preserve historical data integrity.
24. Ensure critical stock operations are safe from duplicate processing and race conditions.

---

# Required Implementation Workflow

Follow this order:

## Step 1 — Audit

Inspect the project and report:

* Current stack
* Current architecture
* Existing database
* Existing authentication
* Existing authorization
* Existing modules
* Existing roles/permissions
* Missing functionality

## Step 2 — Architecture Plan

Create a concise implementation plan based on the actual codebase.

Do not design an architecture that conflicts with existing patterns.

## Step 3 — Authentication

Complete or preserve the existing authentication system.

## Step 4 — Spatie Roles and Permissions

Install/configure Spatie Laravel Permission if necessary.

Then:

1. Configure the User model.
2. Publish required migrations/configuration.
3. Create roles.
4. Create permissions.
5. Create seeders.
6. Create or assign the initial Super Admin.
7. Protect routes and actions.
8. Add tests.

## Step 5 — Database

Create or update the required migrations and relationships.

## Step 6 — Core Modules

Implement in this general order:

1. Categories
2. Suppliers
3. Products
4. Product variants
5. Product/supplier relationships
6. Inventory and stock movements
7. Inbound inventory / supplier receiving
8. Outbound inventory
9. Orders
10. Shipments
11. Dashboard data

## Step 7 — Routes and Controllers

Connect all functionality through clean Laravel routes, controllers, Form Requests, policies, Spatie permissions, and services/actions.

## Step 8 — Basic Inertia Integration

Ensure every completed module can be accessed and tested through the existing Vue + Inertia application.

The UI can remain minimal.

## Step 9 — Tests

Write and run meaningful tests.

## Step 10 — Documentation

Document the architecture and workflows.

## Step 11 — Final Report

Provide a final summary containing:

### Implemented

List all completed modules.

### Spatie Roles and Permissions

List:

* Installed/configured package details
* Roles
* Permissions
* Seeders
* Protected functionality

### Database Changes

List:

* Migrations
* Tables
* Important relationships
* Supplier-related structures
* Inventory-related structures

### Authentication

Explain what was implemented or preserved.

### Supplier Management

Explain:

* Supplier CRUD
* Product relationships
* Receiving workflow
* Historical data handling

### Inventory Logic

Explain exactly how stock is calculated and updated.

### Routes

List important routes.

### Files Changed

Group changed files by:

* Models
* Migrations
* Seeders
* Controllers
* Requests
* Services/Actions
* Policies
* Middleware
* Vue/Inertia pages
* Tests
* Documentation

### Remaining Work

Clearly identify anything intentionally left for the future UI implementation.

---

# Important: Work Against the Existing Codebase

Do not generate a separate demo application.

Do not create a parallel Laravel project.

Do not assume a fresh Laravel installation.

Modify and extend the existing Laravel + Vue + Inertia project directly.

Before adding new functionality, check whether an equivalent:

* Model
* Table
* Service
* Route
* Component
* Authentication system
* Authorization system

already exists.

Reuse and extend existing code whenever practical.

The final result should be a clean, functional, backend-ready inventory management application with:

* Laravel authentication
* Spatie-based role and permission management
* Supplier management
* Product and variant management
* Transaction-safe inventory management
* Supplier receiving/inbound inventory
* Outbound inventory
* Orders
* Shipments
* Dashboard-ready data

The future UI should be implementable primarily by connecting and styling the existing backend functionality, without requiring major backend restructuring.

===

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
- @inertiajs/vue3 (INERTIA_VUE) - v3
- vue (VUE) - v3
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3
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

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

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

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
