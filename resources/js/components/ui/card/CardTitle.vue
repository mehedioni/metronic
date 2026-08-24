<script setup lang="ts">
defineProps<{ description?: string }>();
</script>

<template>
    <h2 class="truncate text-sm font-semibold text-foreground">
        <slot />
    </h2>
    <p v-if="description" class="mt-0.5 text-xs text-muted-foreground">
        {{ description }}
    </p>
</template>
