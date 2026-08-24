<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from 'lucide-vue-next';
import type { Crumb } from '@/components/PageHeader.vue';
import { useSidebar } from '@/composables/useSidebar';
import AppSidebar from '@/layouts/partials/AppSidebar.vue';
import AppTopbar from '@/layouts/partials/AppTopbar.vue';
import FlashMessages from '@/layouts/partials/FlashMessages.vue';

/**
 * Application shell: collapsible sidebar, sticky topbar, flash toasts.
 *
 * Pages own their own heading (see components/PageHeader.vue); this layout
 * only renders the breadcrumb trail in the topbar, because that sits outside
 * the scrolling content.
 */
defineProps<{
    title?: string;
    breadcrumbs?: Crumb[];
}>();

const { collapsed } = useSidebar();
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <AppSidebar />

        <div
            class="flex min-h-svh min-w-0 flex-col transition-[padding] duration-300 ease-out"
            :class="collapsed ? 'lg:ps-18' : 'lg:ps-64'"
        >
            <AppTopbar>
                <template #breadcrumbs>
                    <nav
                        v-if="breadcrumbs?.length"
                        class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground lg:text-sm"
                        aria-label="Breadcrumb"
                    >
                        <template
                            v-for="(crumb, index) in breadcrumbs"
                            :key="crumb.label"
                        >
                            <Link
                                v-if="crumb.href"
                                :href="crumb.href"
                                class="truncate transition-colors hover:text-foreground"
                                >{{ crumb.label }}</Link
                            >
                            <span
                                v-else
                                class="truncate font-semibold text-foreground"
                                >{{ crumb.label }}</span
                            >

                            <ChevronRightIcon
                                v-if="index < breadcrumbs.length - 1"
                                class="size-3.5 shrink-0"
                            />
                        </template>
                    </nav>

                    <span
                        v-else-if="title"
                        class="truncate text-sm font-semibold"
                        >{{ title }}</span
                    >
                </template>
            </AppTopbar>

            <FlashMessages />

            <main class="flex-1 space-y-6 px-4 py-6 lg:px-8">
                <slot />
            </main>
        </div>
    </div>
</template>
