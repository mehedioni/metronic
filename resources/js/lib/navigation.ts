import {
    BoxesIcon,
    ChartColumnIcon,
    ClipboardListIcon,
    ReceiptIcon,
    LayoutGridIcon,
    PackageIcon,
    ShapesIcon,
    SettingsIcon,
    ShieldCheckIcon,
    TruckIcon,
    UsersRoundIcon,
} from 'lucide-vue-next';
import type { Component } from 'vue';

export interface NavLink {
    label: string;
    /** Absent on a link that opens a drawer rather than navigating. */
    href?: string;
    /**
     * Permission name the backend enforces for the destination. Absent means
     * every signed-in user may follow it — a user's own profile, say.
     */
    permission?: string;
    /** Also mark the parent active for these path prefixes. */
    match?: string[];
    /** Opens the settings drawer on this tab instead of visiting a page. */
    settingsTab?: 'general' | 'profile';
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
                    // {
                    //     label: 'Stock Planner',
                    //     href: '/inventory/stock/planner',
                    //     permission: 'inventory.view',
                    // },
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
                    {
                        // Orders in the configured quote status. They are kept
                        // out of the order list, so this is the only screen
                        // that shows them.
                        label: 'Quote List',
                        href: '/inventory/quotes',
                        permission: 'orders.view',
                    },
                    {
                        label: 'Take Order',
                        href: '/inventory/orders/create',
                        permission: 'orders.create',
                    },
                    {
                        label: 'New Quote',
                        href: '/inventory/quotes/create',
                        permission: 'orders.create',
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
                id: 'expenses',
                label: 'Expenses',
                icon: ReceiptIcon,
                links: [
                    {
                        label: 'Expense List',
                        href: '/inventory/expenses',
                        permission: 'expenses.view',
                    },
                ],
            },
            {
                id: 'reports',
                label: 'Reports',
                icon: ChartColumnIcon,
                links: [
                    {
                        label: 'Daily Sales & Profit',
                        href: '/inventory/reports/daily',
                        permission: 'reports.view',
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
                id: 'settings',
                label: 'Settings',
                icon: SettingsIcon,
                links: [
                    {
                        label: 'General',
                        settingsTab: 'general',
                        permission: 'settings.manage',
                    },
                    {
                        // No permission: everyone may edit their own account.
                        label: 'Profile',
                        settingsTab: 'profile',
                    },
                ],
            },
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
