<script setup lang="ts">
import { computed } from 'vue';
import BaseChart from './BaseChart.vue';

/**
 * The gradient area sparkline behind the dashboard's headline figures.
 */
const props = defineProps<{
    name: string;
    data: number[];
    height?: number;
    /** Index into the chart palette, so callers do not name colours. */
    colorIndex?: number;
    /** Prefix for tooltip values, e.g. a currency symbol. */
    prefix?: string;
}>();

const series = computed(() => [{ name: props.name, data: props.data }]);

const options = computed(() => ({
    chart: { sparkline: { enabled: true } },
    stroke: { curve: 'smooth', width: 2.5 },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.25,
            opacityTo: 0.02,
            stops: [0, 90, 100],
        },
    },
    tooltip: {
        fixed: { enabled: false },
        x: { show: false },
        y: {
            formatter: (value: number) =>
                `${props.prefix ?? ''}${value.toLocaleString()}`,
        },
        marker: { show: true },
    },
    ...(props.colorIndex ? { colors: undefined } : {}),
}));
</script>

<template>
    <BaseChart
        type="area"
        :series="series"
        :options="options"
        :height="height ?? 140"
    />
</template>
