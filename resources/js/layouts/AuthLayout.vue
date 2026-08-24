<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Card } from '@/components/ui/card';
import AppLogo from '@/layouts/partials/AppLogo.vue';
import type { SharedData } from '@/types';

/**
 * Shell for the signed-out screens. Separate from AppLayout because there is
 * no navigation to render and no permissions to read.
 */
defineProps<{
    title: string;
    description?: string;
}>();

const page = usePage<SharedData>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted/40 px-4 py-10"
    >
        <AppLogo :label="page.props.app.name" show-label />

        <Card class="w-full max-w-sm p-6">
            <div class="mb-5">
                <h1 class="text-base font-semibold tracking-tight">
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>

            <slot />
        </Card>

        <p class="text-[11px] text-muted-foreground">
            {{ page.props.app.name }} — inventory management
        </p>
    </div>
</template>
