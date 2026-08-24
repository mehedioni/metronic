<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    name?: string | null;
    src?: string | null;
    /** Small green dot for "signed in", as the topbar avatar shows. */
    online?: boolean;
    class?: HTMLAttributes['class'];
}>();

/** First letters of the first two words, which is what the design shows. */
const initials = computed(() =>
    (props.name ?? '?')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join(''),
);
</script>

<template>
    <span class="relative inline-flex shrink-0">
        <span
            :class="
                cn(
                    'flex size-8 items-center justify-center overflow-hidden rounded-full border border-border bg-secondary text-[11px] font-semibold text-secondary-foreground',
                    props.class,
                )
            "
        >
            <img
                v-if="src"
                :src="src"
                :alt="name ?? ''"
                class="size-full object-cover"
            />
            <template v-else>{{ initials }}</template>
        </span>

        <span
            v-if="online"
            class="absolute bottom-0 end-0 size-2.5 rounded-full border-2 border-card bg-success"
        />
    </span>
</template>
