<?php

namespace App\Core\Support;

/**
 * Canonical permission catalogue, grouped by module.
 *
 * Naming convention: "<resource>.<ability>" — always checked by permission,
 * never by role name, so roles stay pure groupings. Seeded by
 * Modules\Access\Database\Seeders\RolePermissionSeeder.
 */
final class Permissions
{
    public const DASHBOARD_VIEW = 'dashboard.view';

    public const CATEGORIES_VIEW = 'categories.view';

    public const CATEGORIES_CREATE = 'categories.create';

    public const CATEGORIES_UPDATE = 'categories.update';

    public const CATEGORIES_DELETE = 'categories.delete';

    public const SUPPLIERS_VIEW = 'suppliers.view';

    public const SUPPLIERS_CREATE = 'suppliers.create';

    public const SUPPLIERS_UPDATE = 'suppliers.update';

    public const SUPPLIERS_DELETE = 'suppliers.delete';

    public const PRODUCTS_VIEW = 'products.view';

    public const PRODUCTS_CREATE = 'products.create';

    public const PRODUCTS_UPDATE = 'products.update';

    public const PRODUCTS_DELETE = 'products.delete';

    public const INVENTORY_VIEW = 'inventory.view';

    public const INVENTORY_CREATE = 'inventory.create';

    public const INVENTORY_ADJUST = 'inventory.adjust';

    public const INVENTORY_DELETE = 'inventory.delete';

    public const ORDERS_VIEW = 'orders.view';

    public const ORDERS_CREATE = 'orders.create';

    public const ORDERS_UPDATE = 'orders.update';

    public const ORDERS_FULFILL = 'orders.fulfill';

    public const ORDERS_CANCEL = 'orders.cancel';

    public const CUSTOMERS_VIEW = 'customers.view';

    public const CUSTOMERS_CREATE = 'customers.create';

    public const CUSTOMERS_UPDATE = 'customers.update';

    public const CUSTOMERS_DELETE = 'customers.delete';

    public const EXPENSES_VIEW = 'expenses.view';

    public const EXPENSES_CREATE = 'expenses.create';

    public const EXPENSES_UPDATE = 'expenses.update';

    public const EXPENSES_DELETE = 'expenses.delete';

    public const REPORTS_VIEW = 'reports.view';

    public const USERS_VIEW = 'users.view';

    public const USERS_CREATE = 'users.create';

    public const USERS_UPDATE = 'users.update';

    public const USERS_DELETE = 'users.delete';

    public const ROLES_VIEW = 'roles.view';

    public const ROLES_CREATE = 'roles.create';

    public const ROLES_UPDATE = 'roles.update';

    public const ROLES_DELETE = 'roles.delete';

    public const PERMISSIONS_VIEW = 'permissions.view';

    public const PERMISSIONS_MANAGE = 'permissions.manage';

    /**
     * All permissions keyed by the group they belong to.
     *
     * @return array<string, array<int, string>>
     */
    public static function groups(): array
    {
        return [
            'dashboard' => [self::DASHBOARD_VIEW],
            'categories' => [
                self::CATEGORIES_VIEW,
                self::CATEGORIES_CREATE,
                self::CATEGORIES_UPDATE,
                self::CATEGORIES_DELETE,
            ],
            'suppliers' => [
                self::SUPPLIERS_VIEW,
                self::SUPPLIERS_CREATE,
                self::SUPPLIERS_UPDATE,
                self::SUPPLIERS_DELETE,
            ],
            'products' => [
                self::PRODUCTS_VIEW,
                self::PRODUCTS_CREATE,
                self::PRODUCTS_UPDATE,
                self::PRODUCTS_DELETE,
            ],
            'inventory' => [
                self::INVENTORY_VIEW,
                self::INVENTORY_CREATE,
                self::INVENTORY_ADJUST,
                self::INVENTORY_DELETE,
            ],
            'orders' => [
                self::ORDERS_VIEW,
                self::ORDERS_CREATE,
                self::ORDERS_UPDATE,
                self::ORDERS_FULFILL,
                self::ORDERS_CANCEL,
            ],
            'customers' => [
                self::CUSTOMERS_VIEW,
                self::CUSTOMERS_CREATE,
                self::CUSTOMERS_UPDATE,
                self::CUSTOMERS_DELETE,
            ],
            'expenses' => [
                self::EXPENSES_VIEW,
                self::EXPENSES_CREATE,
                self::EXPENSES_UPDATE,
                self::EXPENSES_DELETE,
            ],
            'reports' => [self::REPORTS_VIEW],
            'users' => [
                self::USERS_VIEW,
                self::USERS_CREATE,
                self::USERS_UPDATE,
                self::USERS_DELETE,
            ],
            'roles' => [
                self::ROLES_VIEW,
                self::ROLES_CREATE,
                self::ROLES_UPDATE,
                self::ROLES_DELETE,
            ],
            'permissions' => [
                self::PERMISSIONS_VIEW,
                self::PERMISSIONS_MANAGE,
            ],
        ];
    }

    /**
     * Flat list of every permission name.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_merge(...array_values(self::groups()));
    }

    /**
     * Every permission in the given groups.
     *
     * @param  array<int, string>  $groups
     * @return array<int, string>
     */
    public static function forGroups(array $groups): array
    {
        $catalogue = self::groups();

        return array_merge(...array_map(
            fn (string $group): array => $catalogue[$group] ?? [],
            $groups,
        ));
    }
}
