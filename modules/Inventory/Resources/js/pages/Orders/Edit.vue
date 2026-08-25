<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import orderRoutes from '@/routes/inventory/orders';
import OrderForm from '../../components/OrderForm.vue';

const props = defineProps<{
    order: Record<string, any>;
    options: Record<string, any>;
}>();

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Orders', href: orderRoutes.index.url() },
    {
        label: props.order.order_number,
        href: orderRoutes.show.url(props.order.id),
    },
    { label: 'Edit' },
];
</script>

<template>
    <Head :title="`Edit ${props.order.order_number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            :title="`Edit ${props.order.order_number}`"
            description="Lines and totals can change while the order is a draft or pending — that is, before it holds any stock."
            :breadcrumbs="breadcrumbs"
        />

        <OrderForm
            :order="props.order"
            :options="props.options"
            :action="orderRoutes.update.url(props.order.id)"
            method="put"
            submit-label="Save order"
            :cancel-href="orderRoutes.show.url(props.order.id)"
        />
    </AppLayout>
</template>
