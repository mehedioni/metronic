<script setup lang="ts">
import { computed } from 'vue';
import BaseChart from './BaseChart.vue';

const props = defineProps<{
    labels: string[];
    values: number[];
    height?: number;
    /** Text under the centre total, e.g. "orders". */
    totalLabel?: string;
}>();

const options = computed(() => ({
    labels: props.labels,
    stroke: { width: 0 },
    plotOptions: {
        pie: {
            donut: {
                size: '72%',
                labels: {
                    show: true,
                    name: { fontSize: '11px' },
                    value: { fontSize: '18px', fontWeight: 700 },
                    total: {
                        show: true,
                        label: props.totalLabel ?? 'Total',
                        fontSize: '11px',
                    },
                },
            },
        },
    },
    legend: {
        show: true,
        position: 'bottom',
        fontSize: '11px',
        markers: { size: 5 },
        itemMargin: { horizontal: 8, vertical: 2 },
    },
    tooltip: { y: { formatter: (value: number) => value.toLocaleString() } },
}));
</script>

<template>
    <BaseChart
        type="donut"
        :series="values"
        :options="options"
        :height="height ?? 260"
    />
</template>
