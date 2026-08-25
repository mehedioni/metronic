<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    LogOutIcon,
    MenuIcon,
    MoonIcon,
    PanelLeftIcon,
    SearchIcon,
    SunIcon,
    UserIcon,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dropdown,
    DropdownItem,
    DropdownSeparator,
} from '@/components/ui/dropdown';
import { Input } from '@/components/ui/input';
import { useAppearance } from '@/composables/useAppearance';
import { useSidebar, useSidebarShortcuts } from '@/composables/useSidebar';
import type { SharedData } from '@/types';

const page = usePage<SharedData>();
const { resolvedAppearance, updateAppearance } = useAppearance();
const { toggle, openMobile } = useSidebar();

const search = ref('');
const searchInput = ref<HTMLInputElement | null>(null);

// The design advertises ⌘K next to the search box, so it has to work.
useSidebarShortcuts(() => {
    const field = document.getElementById(
        'global-search',
    ) as HTMLInputElement | null;

    field?.focus();
    field?.select();
});

function toggleAppearance() {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}

/**
 * Global search has no backend of its own; it hands the term to the product
 * list, which is the search users actually mean from here.
 */
function submitSearch() {
    if (!search.value.trim()) {
        return;
    }

    router.get('/inventory/products', { search: search.value });
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-dashed border-zinc-200/80 bg-white/95 px-4 backdrop-blur-md dark:border-zinc-800/80 dark:bg-[#121215]/95 lg:px-8"
    >
        <div class="flex min-w-0 items-center gap-2">
            <Button
                variant="ghost"
                size="icon-round"
                class="lg:hidden"
                aria-label="Open navigation"
                @click="openMobile"
            >
                <MenuIcon />
            </Button>

            <Button
                variant="ghost"
                size="icon-round"
                class="hidden lg:inline-flex"
                aria-label="Toggle sidebar"
                @click="toggle"
            >
                <PanelLeftIcon />
            </Button>

            <div class="min-w-0">
                <slot name="breadcrumbs" />
            </div>
        </div>

        <div class="flex items-center gap-1.5 lg:gap-2">
            <form
                class="relative hidden w-48 sm:block md:w-64"
                @submit.prevent="submitSearch"
            >
                <Input
                    id="global-search"
                    ref="searchInput"
                    v-model="search"
                    placeholder="Search products"
                    has-icon
                    class="h-9 rounded-full pe-16"
                >
                    <template #icon><SearchIcon /></template>
                    <template #suffix>
                        <kbd
                            class="rounded border border-border bg-card px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground"
                        >
                            ⌘ K
                        </kbd>
                    </template>
                </Input>
            </form>

            <Button
                variant="ghost"
                size="icon-round"
                :aria-label="
                    resolvedAppearance === 'dark'
                        ? 'Switch to light theme'
                        : 'Switch to dark theme'
                "
                @click="toggleAppearance"
            >
                <SunIcon v-if="resolvedAppearance === 'dark'" />
                <MoonIcon v-else />
            </Button>

            <Dropdown v-if="page.props.auth.user">
                <template #trigger>
                    <button
                        type="button"
                        class="ms-1 cursor-pointer rounded-full ring-2 ring-transparent transition-all hover:ring-border"
                        aria-label="Account menu"
                    >
                        <Avatar :name="page.props.auth.user.name" online />
                    </button>
                </template>

                <div class="px-2.5 py-2">
                    <p class="truncate text-[0.8125rem] font-medium">
                        {{ page.props.auth.user.name }}
                    </p>
                    <p class="truncate text-[11px] text-muted-foreground">
                        {{ page.props.auth.user.email }}
                    </p>
                </div>

                <DropdownSeparator />

                <DropdownItem as-child>
                    <Link href="/access/users" class="flex items-center gap-2">
                        <UserIcon />
                        Team
                    </Link>
                </DropdownItem>

                <DropdownSeparator />

                <DropdownItem destructive @select="logout">
                    <LogOutIcon />
                    Sign out
                </DropdownItem>
            </Dropdown>
        </div>
    </header>
</template>
