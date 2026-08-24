<script setup lang="ts">
import { InboxIcon } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-2 px-6 py-14 text-center"
    >
        <span
            class="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground"
        >
            <slot name="icon"><InboxIcon class="size-5" /></slot>
        </span>

        <p class="text-sm font-medium text-foreground">
            {{ title ?? 'Nothing to show yet' }}
        </p>
        <p v-if="description" class="max-w-sm text-xs text-muted-foreground">
            {{ description }}
        </p>

        <div v-if="$slots.action" class="mt-2">
            <slot name="action" />
        </div>
    </div>
</template>
