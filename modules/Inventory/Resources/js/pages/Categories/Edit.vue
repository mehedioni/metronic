<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import categories from '@/routes/inventory/categories';
import CategoryForm from '../../components/CategoryForm.vue';

const props = defineProps<{
    category: Record<string, any>;
    parents: Array<{ id: number; name: string }>;
    statuses: string[];
}>();

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Categories', href: categories.index.url() },
    { label: props.category.name, href: categories.show.url(props.category.id) },
    { label: 'Edit' },
];
</script>

<template>
    <Head :title="`Edit ${props.category.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            :title="`Edit ${props.category.name}`"
            :breadcrumbs="breadcrumbs"
        />

        <CategoryForm
            :category="props.category"
            :parents="props.parents"
            :statuses="props.statuses"
            :action="categories.update.url(props.category.id)"
            method="put"
            submit-label="Save changes"
            :cancel-href="categories.show.url(props.category.id)"
        />
    </AppLayout>
</template>
