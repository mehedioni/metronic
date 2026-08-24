<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import products from '@/routes/inventory/products';
import ProductForm from '../../components/ProductForm.vue';

const props = defineProps<{
    product: Record<string, any>;
    options: Record<string, any>;
}>();

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Products', href: products.index.url() },
    { label: props.product.name, href: products.show.url(props.product.id) },
    { label: 'Edit' },
];
</script>

<template>
    <Head :title="`Edit ${props.product.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            :title="`Edit ${props.product.name}`"
            :description="props.product.sku ?? undefined"
            :breadcrumbs="breadcrumbs"
        />

        <ProductForm
            :product="props.product"
            :options="props.options"
            :action="products.update.url(props.product.id)"
            method="put"
            submit-label="Save changes"
            :cancel-href="products.show.url(props.product.id)"
        />
    </AppLayout>
</template>
