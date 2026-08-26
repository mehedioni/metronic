<script setup lang="ts">
/**
 * One setting: what it is on the left, the control on the right.
 *
 * The description carries its own weight here — a settings drawer is read by
 * someone deciding whether to touch something, not filling in a form they
 * already understand.
 */
defineProps<{
    label: string;
    description?: string;
    error?: string;
    /** Last row in a card, so it draws no rule beneath itself. */
    last?: boolean;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:gap-5"
        :class="!last && 'border-b border-border/70'"
    >
        <div class="flex flex-col gap-1 sm:basis-1/3">
            <span class="text-2sm font-medium text-foreground">{{ label }}</span>
            <span v-if="description" class="text-2xs text-muted-foreground">
                {{ description }}
            </span>
        </div>

        <div class="min-w-0 sm:basis-2/3">
            <slot />

            <p v-if="error" class="mt-1.5 text-2xs text-danger">{{ error }}</p>
        </div>
    </div>
</template>
