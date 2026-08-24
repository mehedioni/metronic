<script setup lang="ts">
import { computed } from 'vue';
import BaseChart from './BaseChart.vue';

const props = defineProps<{
    series: Array<{ name: string; data: number[] }>;
    categories: string[];
    height?: number;
    horizontal?: boolean;
    stacked?: boolean;
}>();

const options = computed(() => ({
    chart: { type: 'bar', stacked: props.stacked ?? false },
    plotOptions: {
        bar: {
            horizontal: props.horizontal ?? false,
            borderRadius: 4,
            borderRadiusApplication: 'end',
            columnWidth: '45%',
        },
    },
    xaxis: { categories: props.categories },
}));
</script>

<template>
    <BaseChart
        type="bar"
        :series="series"
        :options="options"
        :height="height ?? 240"
    />
</template>
