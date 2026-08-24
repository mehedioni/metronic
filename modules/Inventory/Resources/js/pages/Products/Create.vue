<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import products from '@/routes/inventory/products';
import ProductForm from '../../components/ProductForm.vue';

const props = defineProps<{ options: Record<string, any> }>();

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Products', href: products.index.url() },
    { label: 'Create' },
];
</script>

<template>
    <Head title="Create product" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="Create product"
            description="Add a product to the catalogue. Stock is added separately, through receiving or an adjustment."
            :breadcrumbs="breadcrumbs"
        />

        <ProductForm
            :options="props.options"
            :action="products.store.url()"
            method="post"
            submit-label="Create product"
            :cancel-href="products.index.url()"
        />
    </AppLayout>
</template>
