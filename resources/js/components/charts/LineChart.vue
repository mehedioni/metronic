<script setup lang="ts">
import { computed } from 'vue';
import BaseChart from './BaseChart.vue';

const props = defineProps<{
    series: Array<{ name: string; data: number[] }>;
    categories: string[];
    height?: number;
    /** Dashes the second series, as the sales-activity chart does. */
    dashSecondary?: boolean;
    valuePrefix?: string;
}>();

const options = computed(() => ({
    chart: { type: 'line' },
    stroke: {
        width: props.series.map((_, index) => (index === 0 ? 2.5 : 2)),
        curve: 'smooth',
        dashArray: props.series.map((_, index) =>
            props.dashSecondary && index > 0 ? 5 : 0,
        ),
    },
    markers: { size: 0, hover: { size: 5 } },
    xaxis: { categories: props.categories },
    tooltip: {
        shared: true,
        intersect: false,
        y: {
            formatter: (value: number) =>
                `${props.valuePrefix ?? ''}${value.toLocaleString()}`,
        },
    },
}));
</script>

<template>
    <BaseChart
        type="line"
        :series="series"
        :options="options"
        :height="height ?? 230"
    />
</template>
