<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { MoonIcon, SunIcon } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import { usePermissions } from '@/composables/usePermissions';
import type { SharedData } from '@/types';

defineProps<{
    title?: string;
}>();

const page = usePage<SharedData>();
const { resolvedAppearance, updateAppearance } = useAppearance();
const { can } = usePermissions();

/**
 * Nav entries are filtered by the same permission names the backend
 * enforces, so a user never sees a link they cannot follow.
 */
const navigation = [
    { label: 'Dashboard', href: '/dashboard', permission: 'dashboard.view' },
    {
        label: 'Products',
        href: '/inventory/products',
        permission: 'products.view',
    },
    {
        label: 'Categories',
        href: '/inventory/categories',
        permission: 'categories.view',
    },
    {
        label: 'Suppliers',
        href: '/inventory/suppliers',
        permission: 'suppliers.view',
    },
    { label: 'Stock', href: '/inventory/stock', permission: 'inventory.view' },
    {
        label: 'Movements',
        href: '/inventory/movements',
        permission: 'inventory.view',
    },
    {
        label: 'Receiving',
        href: '/inventory/inbound',
        permission: 'inventory.view',
    },
    { label: 'Orders', href: '/inventory/orders', permission: 'orders.view' },
    {
        label: 'Shipments',
        href: '/inventory/shipments',
        permission: 'shipments.view',
    },
    { label: 'Users', href: '/access/users', permission: 'users.view' },
    { label: 'Roles', href: '/access/roles', permission: 'roles.view' },
];

function toggleAppearance() {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <header class="border-b border-border">
            <div class="flex items-center justify-between px-6 py-4">
                <Link href="/dashboard" class="text-lg font-semibold"
                    >RentMy Admin</Link
                >

                <div class="flex items-center gap-3">
                    <span
                        v-if="page.props.auth.user"
                        class="text-sm text-muted-foreground"
                    >
                        {{ page.props.auth.user.name }}
                    </span>

                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Toggle theme"
                        @click="toggleAppearance"
                    >
                        <SunIcon
                            v-if="resolvedAppearance === 'dark'"
                            class="size-4"
                        />
                        <MoonIcon v-else class="size-4" />
                    </Button>

                    <Button
                        v-if="page.props.auth.user"
                        variant="ghost"
                        @click="logout"
                        >Sign out</Button
                    >
                </div>
            </div>

            <nav
                v-if="page.props.auth.user"
                class="flex flex-wrap gap-1 px-6 pb-3 text-sm"
            >
                <template v-for="item in navigation" :key="item.href">
                    <Link
                        v-if="can(item.permission)"
                        :href="item.href"
                        class="rounded px-3 py-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        {{ item.label }}
                    </Link>
                </template>
            </nav>
        </header>

        <main class="flex-1 px-6 py-8">
            <div
                v-if="page.props.flash?.success"
                class="mb-4 rounded border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm"
            >
                {{ page.props.flash.success }}
            </div>
            <div
                v-if="page.props.flash?.error"
                class="mb-4 rounded border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm"
            >
                {{ page.props.flash.error }}
            </div>

            <h1 v-if="title" class="mb-6 text-2xl font-semibold">
                {{ title }}
            </h1>
            <slot />
        </main>
    </div>
</template>
