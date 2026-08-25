<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import orderRoutes from '@/routes/inventory/orders';
import OrderForm from '../../components/OrderForm.vue';

const props = defineProps<{ options: Record<string, any> }>();

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Orders', href: orderRoutes.index.url() },
    { label: 'Take order' },
];
</script>

<template>
    <Head title="Take order" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="Take order"
            description="Saving records the order and its lines. Stock is reserved later, when the order is confirmed."
            :breadcrumbs="breadcrumbs"
        />

        <OrderForm
            :options="props.options"
            :action="orderRoutes.store.url()"
            method="post"
            submit-label="Create order"
            :cancel-href="orderRoutes.index.url()"
        />
    </AppLayout>
</template>
