<script setup lang="ts">
import { ArrowDownRightIcon, ArrowUpRightIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { percent } from '@/lib/format';

/**
 * Headline figure with an optional period-over-period delta and room for a
 * sparkline underneath.
 */
const props = defineProps<{
    label: string;
    value: string | number;
    /** Percentage change; positive reads as success, negative as danger. */
    delta?: number | null;
    hint?: string;
}>();

const rising = computed(() => (props.delta ?? 0) >= 0);
</script>

<template>
    <Card class="flex flex-col p-5">
        <div
            class="flex items-center justify-between gap-3 border-b border-dashed border-border pb-3"
        >
            <h3 class="truncate text-sm font-semibold text-foreground">
                {{ label }}
            </h3>
            <slot name="header-action" />
        </div>

        <div class="mt-4 flex items-center gap-2.5">
            <span
                class="text-2xl font-bold tracking-tight text-foreground lg:text-3xl"
            >
                {{ value }}
            </span>

            <Badge
                v-if="delta !== null && delta !== undefined"
                :variant="rising ? 'success' : 'danger'"
            >
                <ArrowUpRightIcon v-if="rising" />
                <ArrowDownRightIcon v-else />
                {{ percent(delta) }}
            </Badge>
        </div>

        <p v-if="hint" class="mt-1 text-xs text-muted-foreground">{{ hint }}</p>

        <div v-if="$slots.default" class="mt-auto pt-3">
            <slot />
        </div>
    </Card>
</template>
