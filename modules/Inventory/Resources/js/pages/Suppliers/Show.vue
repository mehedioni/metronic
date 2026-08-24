<script setup lang="ts">
import { Deferred, Head, Link, router, useForm } from '@inertiajs/vue3';
import { PowerIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { FormField, Textarea } from '@/components/form';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { TabPanel, Tabs } from '@/components/ui/tabs';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, number } from '@/lib/format';
import products from '@/routes/inventory/products';
import suppliers from '@/routes/inventory/suppliers';

interface Supplier {
    id: string;
    code: string;
    company_name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    website: string | null;
    city: string | null;
    country: string | null;
    payment_terms: string | null;
    notes: string | null;
    status: string;
}

interface History {
    products_supplied: Array<{ id: string; name: string; sku: string | null }>;
    total_received_quantity: number;
    last_received_at: string | null;
    recent_receipts: Array<{
        id: string;
        reference_number: string;
        status: string;
        items_sum_quantity?: number | null;
    }>;
}

const props = defineProps<{ supplier: Supplier; history?: History }>();

const { can } = usePermissions();

const tab = ref('details');

const form = useForm({
    company_name: props.supplier.company_name,
    contact_name: props.supplier.contact_name ?? '',
    email: props.supplier.email ?? '',
    phone: props.supplier.phone ?? '',
    city: props.supplier.city ?? '',
    country: props.supplier.country ?? '',
    payment_terms: props.supplier.payment_terms ?? '',
    notes: props.supplier.notes ?? '',
});

const tabs = [
    { value: 'details', label: 'Details', count: null },
    { value: 'edit', label: 'Edit', count: null },
];

const breadcrumbs = computed(() => [
    { label: 'Store Inventory' },
    { label: 'Suppliers', href: suppliers.index.url() },
    { label: props.supplier.company_name },
]);

function toggleStatus() {
    router.patch(`/inventory/suppliers/${props.supplier.id}/status`, {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="supplier.company_name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            :title="supplier.company_name"
            :description="supplier.code"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <StatusBadge :status="supplier.status" />

                <Button
                    v-if="can('suppliers.update')"
                    variant="outline"
                    size="dense"
                    @click="toggleStatus"
                >
                    <PowerIcon />
                    {{ supplier.status === 'active' ? 'Deactivate' : 'Activate' }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <Tabs
                    v-model="tab"
                    :tabs="can('suppliers.update') ? tabs : tabs.slice(0, 1)"
                    class="px-5"
                >
                    <TabPanel value="details">
                        <CardContent>
                            <dl class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Contact
                                    </dt>
                                    <dd class="text-[0.8125rem]">
                                        {{ supplier.contact_name ?? '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">Email</dt>
                                    <dd class="text-[0.8125rem]">
                                        {{ supplier.email ?? '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">Phone</dt>
                                    <dd class="text-[0.8125rem]">
                                        {{ supplier.phone ?? '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Location
                                    </dt>
                                    <dd class="text-[0.8125rem]">
                                        {{
                                            [supplier.city, supplier.country]
                                                .filter(Boolean)
                                                .join(', ') || '—'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Payment terms
                                    </dt>
                                    <dd class="text-[0.8125rem]">
                                        {{ supplier.payment_terms ?? '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Website
                                    </dt>
                                    <dd class="truncate text-[0.8125rem]">
                                        {{ supplier.website ?? '—' }}
                                    </dd>
                                </div>
                            </dl>

                            <div v-if="supplier.notes" class="mt-5">
                                <p class="mb-1 text-xs text-muted-foreground">Notes</p>
                                <p
                                    class="whitespace-pre-line text-[0.8125rem] leading-relaxed"
                                >
                                    {{ supplier.notes }}
                                </p>
                            </div>
                        </CardContent>
                    </TabPanel>

                    <TabPanel v-if="can('suppliers.update')" value="edit">
                        <CardContent>
                            <form
                                class="space-y-4"
                                @submit.prevent="
                                    form.put(suppliers.update.url(supplier.id), {
                                        preserveScroll: true,
                                    })
                                "
                            >
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        label="Company name"
                                        :error="form.errors.company_name"
                                    >
                                        <Input v-model="form.company_name" />
                                    </FormField>

                                    <FormField
                                        label="Contact name"
                                        :error="form.errors.contact_name"
                                    >
                                        <Input v-model="form.contact_name" />
                                    </FormField>

                                    <FormField label="Email" :error="form.errors.email">
                                        <Input v-model="form.email" type="email" />
                                    </FormField>

                                    <FormField label="Phone" :error="form.errors.phone">
                                        <Input v-model="form.phone" />
                                    </FormField>

                                    <FormField label="City" :error="form.errors.city">
                                        <Input v-model="form.city" />
                                    </FormField>

                                    <FormField
                                        label="Country"
                                        :error="form.errors.country"
                                        hint="Two-letter code"
                                    >
                                        <Input v-model="form.country" maxlength="2" />
                                    </FormField>

                                    <FormField
                                        label="Payment terms"
                                        :error="form.errors.payment_terms"
                                    >
                                        <Input v-model="form.payment_terms" />
                                    </FormField>
                                </div>

                                <FormField label="Notes" :error="form.errors.notes">
                                    <Textarea v-model="form.notes" :rows="4" />
                                </FormField>

                                <div class="flex justify-end">
                                    <Button
                                        type="submit"
                                        size="dense"
                                        :disabled="form.processing"
                                        >Save changes</Button
                                    >
                                </div>
                            </form>
                        </CardContent>
                    </TabPanel>
                </Tabs>
            </Card>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <template #title>
                            <CardTitle description="Everything received from them"
                                >Receiving</CardTitle
                            >
                        </template>
                    </CardHeader>

                    <Deferred data="history">
                        <template #fallback>
                            <div class="h-24 animate-pulse bg-muted/40" />
                        </template>

                        <CardContent v-if="history">
                            <dl class="space-y-3">
                                <div class="flex items-baseline justify-between">
                                    <dt class="text-xs text-muted-foreground">
                                        Units received
                                    </dt>
                                    <dd class="text-xl font-bold">
                                        {{ number(history.total_received_quantity) }}
                                    </dd>
                                </div>
                                <div class="flex items-baseline justify-between">
                                    <dt class="text-xs text-muted-foreground">
                                        Last received
                                    </dt>
                                    <dd class="text-[0.8125rem]">
                                        {{ date(history.last_received_at) }}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Deferred>
                </Card>

                <Card>
                    <CardHeader>
                        <template #title>
                            <CardTitle>Products supplied</CardTitle>
                        </template>
                    </CardHeader>

                    <Deferred data="history">
                        <template #fallback>
                            <div class="h-24 animate-pulse bg-muted/40" />
                        </template>

                        <ul v-if="history" class="divide-y divide-border">
                            <li
                                v-for="product in history.products_supplied"
                                :key="product.id"
                                class="px-5 py-2.5 text-[0.8125rem]"
                            >
                                <Link
                                    :href="products.show.url(product.id)"
                                    class="hover:underline"
                                    >{{ product.name }}</Link
                                >
                                <span
                                    class="ms-1 font-mono text-[11px] text-muted-foreground"
                                    >{{ product.sku }}</span
                                >
                            </li>
                            <li
                                v-if="!history.products_supplied.length"
                                class="px-5 py-6 text-center text-xs text-muted-foreground"
                            >
                                No linked products.
                            </li>
                        </ul>
                    </Deferred>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
