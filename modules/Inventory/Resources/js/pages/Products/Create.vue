<script setup lang="ts">
import type { Paginated } from '@/types';
import Index from './Index.vue';

const props = defineProps<{
    products?: Paginated<any>;
    filters?: Record<string, unknown>;
    options?: Record<string, any>;
}>();
</script>

<template>
    <Index
        :products="props.products || { data: [], links: [], from: 1, to: 10, total: 10, current_page: 1, last_page: 1, per_page: 10 }"
        :filters="props.filters || {}"
        :options="props.options || {}"
        :show-create-modal="true"
    />
</template>
