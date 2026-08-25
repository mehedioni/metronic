<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from 'lucide-vue-next';

export interface Crumb {
    label: string;
    href?: string;
}

defineProps<{
    title: string;
    description?: string;
    breadcrumbs?: Crumb[];
}>();
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <nav
                v-if="breadcrumbs?.length"
                class="mb-1 flex items-center gap-1.5 text-xs text-muted-foreground"
                aria-label="Breadcrumb"
            >
                <template
                    v-for="(crumb, index) in breadcrumbs"
                    :key="crumb.label"
                >
                    <Link
                        v-if="crumb.href"
                        :href="crumb.href"
                        class="transition-colors hover:text-foreground"
                        >{{ crumb.label }}</Link
                    >
                    <span v-else class="font-medium text-foreground">{{
                        crumb.label
                    }}</span>

                    <ChevronRightIcon
                        v-if="index < breadcrumbs.length - 1"
                        class="size-3.5 shrink-0"
                    />
                </template>
            </nav>

            <h1
                class="truncate text-xl font-semibold text-zinc-900 dark:text-white"
            >
                {{ title }}
            </h1>
            <p v-if="description" class="mt-0.5 text-xs text-muted-foreground">
                {{ description }}
            </p>
        </div>

        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
