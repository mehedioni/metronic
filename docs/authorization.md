# Authorization

All roles and permissions are handled by **Spatie Laravel Permission** (v6,
`spatie/laravel-permission` — the only dependency added). No custom
role/permission tables exist.

## The catalogue lives in code

`App\Core\Support\Permissions` is the single source of truth: constants grouped
by resource, plus `groups()`, `all()` and `forGroups()`. Adding a permission
means adding a constant and re-running the seeder — never inserting a row by
hand.

Naming is always `<resource>.<ability>`:

```
dashboard.view
categories.view|create|update|delete
suppliers.view|create|update|delete
products.view|create|update|delete
inventory.view|create|adjust|delete
orders.view|create|update|cancel
shipments.view|create|update|delete
users.view|create|update|delete
roles.view|create|update|delete
permissions.view|manage
```

## Roles

`App\Core\Support\Roles` defines the default roles and the permissions each one
groups (`Roles::permissionMap()`):

| Role | Grants |
| --- | --- |
| Super Admin | everything, via `Gate::before` — holds no explicit permissions |
| Admin | every permission in the catalogue |
| Manager | dashboard, categories, suppliers, products, inventory, orders, shipments |
| Inventory Manager | dashboard, categories, suppliers, products, inventory + view orders, view/create/update shipments |
| Staff | read-only across the app, plus `orders.create` |

Roles exist only to group permissions. Controllers and policies always check
**permissions**, never role names. The two exceptions are deliberate and
documented in code: `Gate::before` (Super Admin bypass) and the guards that
stop non-Super-Admins granting Super Admin.

## Super Admin

`App\Providers\AppServiceProvider::registerSuperAdminBypass()`:

```php
Gate::before(fn (User $user) => $user->hasRole(Roles::SUPER_ADMIN) ? true : null);
```

Returning `null` for everyone else leaves normal policy resolution untouched.
Because the bypass is not permission-backed, a Super Admin automatically holds
abilities added after the role was seeded.

### Initial Super Admin

Configured in `config/access.php` from the environment:

```dotenv
SUPER_ADMIN_NAME="Super Admin"
SUPER_ADMIN_EMAIL=admin@example.com
SUPER_ADMIN_PASSWORD=change-me-immediately
```

`php artisan db:seed` runs `RolePermissionSeeder` then `SuperAdminSeeder`, which
creates **or promotes** that account. For an existing install:

```bash
php artisan access:super-admin user@example.com
```

## Four layers of protection

1. **Route middleware** — every module route carries
   `permission:<name>` (Spatie's `PermissionMiddleware`, aliased in
   `bootstrap/app.php`). This rejects the request before a controller is even
   resolved.
2. **Policies** — `Gate::authorize()` in every controller method, resolved from
   `#[UsePolicy]` attributes on the models (`Gate::policy()` in
   `AccessServiceProvider` for `User` and the Spatie models, which cannot carry
   attributes).
3. **Service-level rules** — `UserService` and `RoleService` refuse privilege
   escalation; `CategoryService`, `SupplierService`, `ProductService`,
   `InboundReceiptService`, `OrderService` and `ShipmentService` refuse
   deletions that would corrupt history.
4. **Domain guards** — status-transition and idempotency checks inside the
   Actions, so a hand-crafted request cannot double-process a document.

Route middleware alone is not enough: a resource permission such as
`suppliers.view` guards a whole resource group, and it is the policy that
decides whether *this* actor may update or delete *this* record.

## Anti-escalation rules

Enforced in `Modules\Access\Services\UserService` / `RoleService`, raising
`RoleEscalationException` (rendered as 403 for JSON, a `roles` field error for
Inertia):

- An actor may only grant a role whose **every** permission they hold
  themselves.
- Only a Super Admin may grant Super Admin.
- The **last** Super Admin cannot be demoted.
- A user may not deactivate or delete themselves (`UserPolicy`).
- The Super Admin role is not editable, and the seeded default roles cannot be
  deleted or renamed (`RolePolicy`, `RoleService`).
- Permissions are read-only through the API — the catalogue is code.
