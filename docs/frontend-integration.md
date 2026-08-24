# Frontend integration

The current Vue pages are deliberate placeholders. Replacing them with the final
UI should not require touching the database, authentication, authorization,
services or actions.

## What to reuse

| Concern | Use |
| --- | --- |
| Route URLs | Wayfinder — `import products from '@/routes/inventory/products'`, then `products.index.url()`. Never hardcode a path. |
| Signed-in user, roles, permissions | `usePage().props.auth` (see `resources/js/types/index.d.ts`) |
| Permission checks in the UI | `resources/js/composables/usePermissions.ts` — `can()` / `canAny()` |
| Flash messages | `usePage().props.flash.success` / `.error` |
| Pagination | pages receive a Laravel paginator; `Paginated<T>` type + `components/Pagination.vue` |
| Page shell | `layouts/AppLayout.vue` (nav is already permission-filtered) |

## Page names and props

Module pages are addressed `Module::Path/Name` (resolved in
`resources/js/app.ts`).

| Route name | Page | Key props |
| --- | --- | --- |
| `dashboard` | `Inventory::Dashboard` | `statistics` (deferred) |
| `inventory.categories.index` | `Inventory::Categories/Index` | `categories`, `filters`, `statuses`, `parents` |
| `inventory.categories.show` | `Inventory::Categories/Show` | `category` |
| `inventory.suppliers.index` | `Inventory::Suppliers/Index` | `suppliers`, `filters`, `statuses` |
| `inventory.suppliers.show` | `Inventory::Suppliers/Show` | `supplier`, `history` (deferred) |
| `inventory.products.index` | `Inventory::Products/Index` | `products`, `filters`, `options` |
| `inventory.products.show` | `Inventory::Products/Show` | `product`, `options` |
| `inventory.stock.index` | `Inventory::Stock/Index` | `items`, `filters`, `categories`, `movementTypes` |
| `inventory.movements.index` | `Inventory::Movements/Index` | `movements`, `filters`, `types` |
| `inventory.inbound.index` / `.show` | `Inventory::Inbound/Index` / `Show` | `receipts` / `receipt`, `allowedTransitions`, `options` |
| `inventory.orders.index` / `.show` | `Inventory::Orders/Index` / `Show` | `orders` / `order`, `allowedTransitions`, `options` |
| `inventory.shipments.index` / `.show` | `Inventory::Shipments/Index` / `Show` | `shipments` / `shipment`, `allowedTransitions`, `statuses` |
| `access.users.index` / `.show` | `Access::Users/Index` / `Show` | `users` / `user`, `roles` |
| `access.roles.index` / `.show` | `Access::Roles/Index` / `Show` | `roles` / `role`, `permissionGroups` |
| `access.permissions.index` | `Access::Permissions/Index` | `groups`, `assigned` |

`allowedTransitions` is always the statuses the current record may move to — use
it to render actions instead of re-deriving the rules in the frontend.

## Action endpoints

| Action | Route |
| --- | --- |
| Adjust stock | `POST inventory.stock.adjust` |
| Receive a receipt | `POST inventory.inbound.receive` |
| Cancel a receipt | `POST inventory.inbound.cancel` |
| Confirm an order | `POST inventory.orders.confirm` |
| Cancel an order | `POST inventory.orders.cancel` |
| Create a shipment for an order | `POST inventory.orders.shipments.store` |
| Dispatch a shipment | `POST inventory.shipments.dispatch` |
| Change shipment status | `POST inventory.shipments.transition` |
| Toggle supplier status | `PATCH inventory.suppliers.status` |
| Toggle user status | `PATCH access.users.status` |

## Read-only JSON API

Under `/api/v1`, for integrations and any future non-Inertia client:
`GET dashboard`, `GET products`, `GET products/{product}`, `GET stock/items`,
`GET stock/movements`. Responses use the `ApiResponse` envelope
(`{success, message, data}`).

Write endpoints were intentionally **not** duplicated here: every write path
(receiving, ordering, dispatching) has exactly one implementation in the
services/actions. To add a JSON write endpoint, call the same action — do not
re-implement the rule.

## Validation errors

Form Requests return standard Laravel validation errors, so Inertia's
`useForm().errors` works unchanged. Domain rule violations
(insufficient stock, illegal status change, restricted deletion) also arrive as
field errors — `quantity`, `status`, `parent_id`, `inventory` — rather than as a
500, so a form can display them the same way.

## Building

Generated Wayfinder files under `resources/js/{actions,routes,wayfinder}` are
gitignored and produced by the Vite plugin on `npm run dev` / `npm run build`.
Run `php artisan wayfinder:generate --with-form --no-interaction` before
`npm run types:check` if the plugin has not run yet.
