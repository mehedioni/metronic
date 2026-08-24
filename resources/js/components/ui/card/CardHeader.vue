<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    /** Dashed separators are the design's default for card headers. */
    solid?: boolean;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <header
        :class="
            cn(
                'flex flex-wrap items-center justify-between gap-3 border-b px-5 py-3.5',
                solid ? 'border-solid border-border' : 'border-dashed border-border',
                props.class,
            )
        "
    >
        <div class="min-w-0">
            <slot name="title" />
        </div>
        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
