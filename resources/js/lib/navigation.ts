import {
    BoxesIcon,
    ClipboardListIcon,
    LayoutGridIcon,
    PackageIcon,
    ShapesIcon,
    ShieldCheckIcon,
    TruckIcon,
    UsersRoundIcon,
} from 'lucide-vue-next';
import type { Component } from 'vue';

export interface NavLink {
    label: string;
    href: string;
    /** Permission name the backend enforces for the destination. */
    permission: string;
    /** Also mark the parent active for these path prefixes. */
    match?: string[];
}

export interface NavGroup {
    /** Stable id, used to remember which accordion sections are open. */
    id: string;
    label: string;
    icon: Component;
    links: NavLink[];
}

export interface NavSection {
    /** Small uppercase heading above a run of groups. */
    heading?: string;
    groups: NavGroup[];
}

/**
 * The sidebar, mirroring the Metronic store-inventory structure.
 *
 * Every link names the permission that guards its route, and the sidebar
 * hides links — and then empty groups — the signed-in user cannot follow.
 * This only tidies the UI: middleware and a policy still authorize each
 * request.
 */
export const navigation: NavSection[] = [
    {
        groups: [
            {
                id: 'dashboards',
                label: 'Dashboards',
                icon: LayoutGridIcon,
                links: [
                    {
                        label: 'Default',
                        href: '/dashboard',
                        permission: 'dashboard.view',
                    },
                ],
            },
        ],
    },
    {
        heading: 'Store Inventory',
        groups: [
            {
                id: 'inventory',
                label: 'Inventory',
                icon: BoxesIcon,
                links: [
                    {
                        label: 'All Stock',
                        href: '/inventory/stock',
                        permission: 'inventory.view',
                    },
                    {
                        label: 'Stock Planner',
                        href: '/inventory/stock/planner',
                        permission: 'inventory.view',
                    },
                    {
                        label: 'Inbound Stock',
                        href: '/inventory/inbound',
                        permission: 'inventory.view',
                    },
                    {
                        label: 'Outbound Stock',
                        href: '/inventory/movements?direction_flow=outbound',
                        permission: 'inventory.view',
                    },
                    {
                        label: 'Stock Movements',
                        href: '/inventory/movements',
                        permission: 'inventory.view',
                    },
                ],
            },
            {
                id: 'products',
                label: 'Products',
                icon: PackageIcon,
                links: [
                    {
                        label: 'Product List',
                        href: '/inventory/products',
                        permission: 'products.view',
                    },
                    {
                        label: 'Create Product',
                        href: '/inventory/products/create',
                        permission: 'products.create',
                    },
                ],
            },
            {
                id: 'categories',
                label: 'Categories',
                icon: ShapesIcon,
                links: [
                    {
                        label: 'Category List',
                        href: '/inventory/categories',
                        permission: 'categories.view',
                    },
                    {
                        label: 'Create Category',
                        href: '/inventory/categories/create',
                        permission: 'categories.create',
                    },
                ],
            },
            {
                id: 'orders',
                label: 'Orders',
                icon: ClipboardListIcon,
                links: [
                    {
                        label: 'Order List',
                        href: '/inventory/orders',
                        permission: 'orders.view',
                    },
                ],
            },
            {
                id: 'customers',
                label: 'Customers',
                icon: UsersRoundIcon,
                links: [
                    {
                        label: 'Customer List',
                        href: '/inventory/customers',
                        permission: 'customers.view',
                    },
                ],
            },
            {
                id: 'suppliers',
                label: 'Suppliers',
                icon: TruckIcon,
                links: [
                    {
                        label: 'Supplier List',
                        href: '/inventory/suppliers',
                        permission: 'suppliers.view',
                    },
                ],
            },
        ],
    },
    {
        heading: 'Administration',
        groups: [
            {
                id: 'access',
                label: 'Access',
                icon: ShieldCheckIcon,
                links: [
                    {
                        label: 'Users',
                        href: '/access/users',
                        permission: 'users.view',
                    },
                    {
                        label: 'Roles',
                        href: '/access/roles',
                        permission: 'roles.view',
                    },
                    {
                        label: 'Permissions',
                        href: '/access/permissions',
                        permission: 'permissions.view',
                    },
                ],
            },
        ],
    },
];
