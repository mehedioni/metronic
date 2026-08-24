<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import categories from '@/routes/inventory/categories';
import CategoryForm from '../../components/CategoryForm.vue';

const props = defineProps<{
    parents: Array<{ id: string; name: string }>;
    statuses: string[];
}>();

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Categories', href: categories.index.url() },
    { label: 'Create' },
];
</script>

<template>
    <Head title="Create category" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader title="Create category" :breadcrumbs="breadcrumbs" />

        <CategoryForm
            :parents="props.parents"
            :statuses="props.statuses"
            :action="categories.store.url()"
            method="post"
            submit-label="Create category"
            :cancel-href="categories.index.url()"
        />
    </AppLayout>
</template>
