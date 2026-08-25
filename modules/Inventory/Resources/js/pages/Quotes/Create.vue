<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import quoteRoutes from '@/routes/inventory/quotes';
import OrderForm from '../../components/OrderForm.vue';

interface StatusOption {
    id: number;
    key: string;
    label: string;
    variant: string;
}

const props = defineProps<{
    status: StatusOption;
    options: Record<string, any>;
}>();

const breadcrumbs = [
    { label: 'Store Inventory' },
    { label: 'Orders' },
    { label: `${props.status.label}s`, href: quoteRoutes.index.url() },
    { label: `New ${props.status.label.toLowerCase()}` },
];
</script>

<template>
    <Head :title="`New ${props.status.label.toLowerCase()}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            :title="`New ${props.status.label.toLowerCase()}`"
            :description="`Saved as ${props.status.label.toLowerCase()} — nothing is reserved until the order is confirmed.`"
            :breadcrumbs="breadcrumbs"
        />

        <!-- The status field is omitted: the endpoint stores the quote status
             regardless of what a form sends, which is what makes it a quote. -->
        <OrderForm
            :options="props.options"
            :action="quoteRoutes.store.url()"
            method="post"
            :submit-label="`Create ${props.status.label.toLowerCase()}`"
            :cancel-href="quoteRoutes.index.url()"
            hide-status
        />
    </AppLayout>
</template>
