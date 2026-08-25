<?php

namespace App\Core\Support;

/**
 * Default roles and the permissions each one groups.
 *
 * Roles are never checked directly in controllers or policies — they only
 * exist to bundle permissions. Super Admin is the single exception: it is
 * short-circuited in App\Providers\AppServiceProvider via Gate::before so it
 * implicitly holds every permission, present and future.
 */
final class Roles
{
    public const SUPER_ADMIN = 'Super Admin';

    public const ADMIN = 'Admin';

    public const MANAGER = 'Manager';

    public const INVENTORY_MANAGER = 'Inventory Manager';

    public const STAFF = 'Staff';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::SUPER_ADMIN,
            self::ADMIN,
            self::MANAGER,
            self::INVENTORY_MANAGER,
            self::STAFF,
        ];
    }

    /**
     * Permission names granted to each role. Super Admin is intentionally
     * absent — Gate::before grants it everything.
     *
     * @return array<string, array<int, string>>
     */
    public static function permissionMap(): array
    {
        return [
            self::ADMIN => Permissions::all(),
            self::MANAGER => Permissions::forGroups([
                'dashboard', 'categories', 'suppliers', 'products', 'inventory', 'orders', 'customers',
                'expenses', 'reports',
            ]),
            self::INVENTORY_MANAGER => array_merge(
                Permissions::forGroups(['dashboard', 'categories', 'suppliers', 'products', 'inventory']),
                [
                    Permissions::ORDERS_VIEW,
                    Permissions::ORDERS_FULFILL,
                    Permissions::CUSTOMERS_VIEW,
                    Permissions::REPORTS_VIEW,
                ],
            ),
            self::STAFF => [
                Permissions::DASHBOARD_VIEW,
                Permissions::CATEGORIES_VIEW,
                Permissions::SUPPLIERS_VIEW,
                Permissions::PRODUCTS_VIEW,
                Permissions::INVENTORY_VIEW,
                Permissions::ORDERS_VIEW,
                Permissions::ORDERS_CREATE,
                Permissions::CUSTOMERS_VIEW,
            ],
        ];
    }
}
