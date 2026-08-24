<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue';
import { useChartTheme } from '@/composables/useChartTheme';

/**
 * Thin wrapper that merges a chart's own options over the shared theme and
 * re-reads the design tokens whenever light/dark flips — Apex holds colours
 * as JavaScript values, so it has to be told.
 */
const props = defineProps<{
    type: 'area' | 'line' | 'bar' | 'donut' | 'radialBar';
    series: unknown;
    options?: Record<string, unknown>;
    height?: number | string;
}>();

/**
 * ApexCharts is ~500 kB, needs a real DOM, and only a few screens draw a
 * chart — so it is imported on demand rather than registered globally.
 */
const ApexChart = defineAsyncComponent(() =>
    import('vue3-apexcharts').then((module) => module.default),
);

const { base, refresh, resolvedAppearance } = useChartTheme();

/** ApexCharts needs a real DOM, so nothing renders until the client mounts. */
const mounted = ref(false);

onMounted(() => (mounted.value = true));

watch(resolvedAppearance, () => refresh());

const merged = computed(() => mergeDeep(base.value, props.options ?? {}));
const key = computed(() => `${props.type}-${resolvedAppearance.value}`);

/**
 * Recursive plain-object merge. Arrays are replaced, not concatenated: a
 * chart passing `colors` or `yaxis` means to override the theme's, not to
 * append to it.
 */
function mergeDeep(
    target: Record<string, any>,
    source: Record<string, any>,
): Record<string, any> {
    const result: Record<string, any> = { ...target };

    for (const [key, value] of Object.entries(source)) {
        const existing = result[key];

        result[key] =
            isPlainObject(existing) && isPlainObject(value)
                ? mergeDeep(existing, value)
                : value;
    }

    return result;
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
</script>

<template>
    <!-- Re-keying on the theme forces a clean re-render; Apex does not
         reliably restyle axes and grids through updateOptions alone. -->
    <ApexChart
        v-if="mounted"
        :key="key"
        :type="type"
        :height="height ?? 240"
        :series="series"
        :options="merged"
    />
    <div
        v-else
        class="animate-pulse rounded-md bg-muted/40"
        :style="{ height: `${Number(height ?? 240)}px` }"
    />
</template>
