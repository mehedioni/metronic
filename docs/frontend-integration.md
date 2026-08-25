# Frontend

The UI is a Metronic-style store-inventory admin built on Vue 3 + Inertia.
Every screen reads props a controller sends; no page holds business rules of
its own, and no control exists without a backend behind it.

## Shell

| Concern | Use |
| --- | --- |
| Page shell | `layouts/AppLayout.vue` — sidebar, topbar, flash toasts. Pass `breadcrumbs` for the topbar trail. |
| Signed-out screens | `layouts/AuthLayout.vue` |
| Sidebar contents | `lib/navigation.ts`. Each link names the permission guarding its route; links, then empty groups, are hidden from users who cannot follow them. |
| Store name | `usePage().props.app.name`, shared from `config('app.name')`. Never hardcode it. |
| Route URLs | Wayfinder — `import products from '@/routes/inventory/products'`, then `products.index.url()`. Never hardcode a path. |
| Permission checks | `composables/usePermissions.ts` — `can()` / `canAny()`. This only hides controls; middleware and a policy still authorize every request. |
| Flash messages | rendered by `layouts/partials/FlashMessages.vue`; nothing to wire per page. |

Where a Wayfinder import would collide with a prop of the same name (a
`products` prop and the `products` routes), the import is aliased —
`productRoutes` — because Vue exposes both to the template.

## Lists

Three pieces cover every list screen:

| Piece | Responsibility |
| --- | --- |
| `composables/useTableQuery.ts` | search, sort, page size and filters, all round-tripped to the server. Sorting the current page in the browser would silently sort one page of the data. |
| `components/DataTable.vue` | sortable headers, row selection, loading and empty states. A column is rendered by a `#cell-<key>` slot; dots in a key become underscores (`product.name` → `#cell-product_name`) because a slot name containing a dot is parsed as a directive modifier. |
| `components/TableToolbar.vue` | search box, filter slot, page size, CSV export, bulk-action slot. |

Every list endpoint accepts `sort` and `direction`. The column must be on that
service's allowlist (`QuerySorter` + a `SORTABLE` constant per service);
anything else falls back to the default order rather than erroring, so a stale
bookmarked URL still renders.

`useRowSelection` clears the selection when the page changes — a hidden
selection is a bulk action waiting to surprise someone. `useCsvExport` exports
what is on screen, and prefixes cells starting with `=`, `+`, `-` or `@` so a
spreadsheet cannot execute them.

## Charts

`components/charts/*` wrap ApexCharts. `useChartTheme` reads the CSS custom
properties off the root element, so changing a token in `app.css` moves the
charts too, and a theme switch re-renders them (Apex holds colours as
JavaScript values and cannot inherit from the stylesheet). ApexCharts is
imported on demand inside `BaseChart.vue`, which keeps it out of the entry
bundle and out of SSR, where it has no DOM.

## Page names and props

Module pages are addressed `Module::Path/Name` (resolved in
`resources/js/app.ts`).

| Route name | Page | Key props |
| --- | --- | --- |
| `dashboard` | `Inventory::Dashboard` | `statistics` (deferred) |
| `inventory.categories.index` | `Inventory::Categories/Index` | `categories`, `filters`, `statuses`, `parents` |
| `inventory.categories.create` / `.edit` | `Inventory::Categories/Create` / `Edit` | `parents`, `statuses`, (`category`) |
| `inventory.categories.show` | `Inventory::Categories/Show` | `category` |
| `inventory.suppliers.index` | `Inventory::Suppliers/Index` | `suppliers`, `filters`, `statuses` |
| `inventory.suppliers.show` | `Inventory::Suppliers/Show` | `supplier`, `history` (deferred) |
| `inventory.customers.index` | `Inventory::Customers/Index` | `customers`, `filters`, `statuses` |
| `inventory.customers.show` | `Inventory::Customers/Show` | `customer`, `history` (deferred) |
| `inventory.products.index` | `Inventory::Products/Index` | `products`, `filters`, `options` |
| `inventory.products.create` / `.edit` | `Inventory::Products/Create` / `Edit` | `options`, (`product`) |
| `inventory.products.show` | `Inventory::Products/Show` | `product`, `options` |
| `inventory.stock.index` | `Inventory::Stock/Index` | `items`, `filters`, `categories`, `movementTypes` |
| `inventory.stock.planner` | `Inventory::Stock/Planner` | `items` (each row carries a `plan`), `filters`, `categories`, `summary` (deferred) |
| `inventory.movements.index` | `Inventory::Movements/Index` | `movements`, `filters`, `types` |
| `inventory.inbound.index` / `.show` | `Inventory::Inbound/Index` / `Show` | `receipts` / `receipt`, `allowedTransitions`, `options` |
| `inventory.orders.index` / `.show` | `Inventory::Orders/Index` / `Show` | `orders` / `order`, `allowedTransitions`, `options` |
| `inventory.orders.create` / `.edit` | `Inventory::Orders/Create` / `Edit` | `options` (customers, products with variants and stock, statuses), (`order`) |
| `access.users.index` / `.show` | `Access::Users/Index` / `Show` | `users` / `user`, `roles` |
| `access.roles.index` / `.show` | `Access::Roles/Index` / `Show` | `roles` / `role`, `permissionGroups` |
| `access.permissions.index` | `Access::Permissions/Index` | `groups`, `assigned` |

`allowedTransitions` is always the statuses the current record may move to —
use it to render actions instead of re-deriving the rules in the frontend.

## Action endpoints

| Action | Route |
| --- | --- |
| Adjust stock | `POST inventory.stock.adjust` |
| Receive a receipt | `POST inventory.inbound.receive` |
| Cancel a receipt | `POST inventory.inbound.cancel` |
| Take an order | `POST inventory.orders.store` — `customer_id` **or** `customer_name`, plus `items[]` |
| Confirm an order | `POST inventory.orders.confirm` |
| Fulfil an order | `POST inventory.orders.fulfill` — optional `lines[<order_item_id>]` for a partial handover; sending none fulfils everything outstanding |
| Cancel an order | `POST inventory.orders.cancel` |
| Toggle supplier status | `PATCH inventory.suppliers.status` |
| Toggle customer status | `PATCH inventory.customers.status` |
| Toggle user status | `PATCH access.users.status` |

## Errors

Form Requests return standard Laravel validation errors, so `useForm().errors`
works unchanged.

Domain rule violations arrive as field errors too, but keyed by the rule
rather than by an input: `inventory` (restricted deletion, already processed),
`quantity` (insufficient stock), `status` (illegal transition), `parent_id`
(circular category). Those keys often match nothing on screen, so an action
like "delete" or "post to stock" reads them through
`composables/usePageErrors.ts` and shows them in its own dialog.

## Building

Requires PHP 8.4+ and Node 20.12+ (Vite 8).

Generated Wayfinder files under `resources/js/{actions,routes,wayfinder}` are
gitignored and produced by the Vite plugin on `npm run dev` / `npm run build`.
Run `php artisan wayfinder:generate --with-form --no-interaction` before
`npm run types:check` if the plugin has not run yet.

`php artisan migrate:fresh --seed` seeds a demo catalogue, stock, receiving and
sales history in `local`, so every screen has something to render.
