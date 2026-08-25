<script setup lang="ts">
import type { Paginated } from '@/types';
import Index from './Index.vue';

interface CategoryRow {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    status: string;
    products_count: number;
    parent: { id: number; name: string } | null;
}

const props = defineProps<{
    category: CategoryRow;
    categories: Paginated<CategoryRow>;
    filters: Record<string, unknown>;
    statuses: string[];
    parents: Array<{ id: number; name: string }>;
}>();
</script>

<template>
    <Index
        :categories="props.categories"
        :filters="props.filters ?? {}"
        :statuses="props.statuses"
        :parents="props.parents"
        :initial-viewing-category="props.category"
    />
</template>
