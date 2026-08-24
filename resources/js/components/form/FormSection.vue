<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <section
        class="flex flex-col overflow-hidden rounded-md border border-border bg-card shadow-xs"
    >
        <header
            class="flex min-h-9.5 flex-wrap items-center justify-between gap-2 border-b border-border bg-muted/60 px-5 py-2"
        >
            <div class="min-w-0">
                <h3
                    class="truncate text-xs font-semibold tracking-tight text-foreground"
                >
                    {{ title }}
                </h3>
                <p v-if="description" class="text-[11px] text-muted-foreground">
                    {{ description }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </header>

        <div class="space-y-4 p-5">
            <slot />
        </div>
    </section>
</template>
