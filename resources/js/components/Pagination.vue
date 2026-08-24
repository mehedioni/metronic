<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PaginationLink } from '@/types';

const props = defineProps<{
    links: PaginationLink[];
    from?: number | null;
    to?: number | null;
    total?: number;
}>();
</script>

<template>
    <div
        v-if="props.links.length > 3"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3"
    >
        <p class="text-sm text-muted-foreground">
            Showing {{ props.from ?? 0 }}–{{ props.to ?? 0 }} of
            {{ props.total ?? 0 }}
        </p>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in props.links" :key="link.label">
                <span
                    v-if="!link.url"
                    class="rounded px-3 py-1 text-sm text-muted-foreground"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    class="rounded px-3 py-1 text-sm"
                    :class="
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-muted'
                    "
                    preserve-scroll
                >
                    <!-- Paginator labels are HTML entities (&laquo;, &raquo;). -->
                    <span v-html="link.label" />
                </Link>
            </template>
        </div>
    </div>
</template>
