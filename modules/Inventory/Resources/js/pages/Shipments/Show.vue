<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import DataCard from '@/components/DataCard.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';

interface Shipment {
    id: string;
    shipment_number: string;
    status: string;
    carrier: string | null;
    tracking_number: string | null;
    shipped_at: string | null;
    delivered_at: string | null;
    order: { id: string; order_number: string; customer_name: string };
    items: Array<{
        id: string;
        quantity: number;
        order_item: {
            id: string;
            product: { name: string };
            variant: { sku: string } | null;
        };
    }>;
}

const props = defineProps<{ shipment: Shipment; allowedTransitions: string[] }>();

const { can } = usePermissions();

const dispatchForm = useForm({});
const transitionForm = useForm({ status: '' });

function dispatchShipment() {
    dispatchForm.post(`/inventory/shipments/${props.shipment.id}/dispatch`, {
        preserveScroll: true,
    });
}

function transition() {
    transitionForm.post(`/inventory/shipments/${props.shipment.id}/transition`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="shipment.shipment_number" />

    <AppLayout :title="shipment.shipment_number">
        <div class="space-y-6">
            <DataCard title="Shipment">
                <template #actions>
                    <Button
                        v-if="can('shipments.update') && !shipment.shipped_at"
                        :disabled="dispatchForm.processing"
                        @click="dispatchShipment"
                        >Dispatch &amp; deduct stock</Button
                    >
                </template>

                <dl class="grid gap-2 p-4 text-sm sm:grid-cols-2">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Status</dt>
                        <dd class="capitalize">{{ shipment.status }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Order</dt>
                        <dd>
                            <Link
                                :href="`/inventory/orders/${shipment.order.id}`"
                                class="underline"
                                >{{ shipment.order.order_number }}</Link
                            >
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Carrier</dt>
                        <dd>{{ shipment.carrier ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Tracking</dt>
                        <dd>{{ shipment.tracking_number ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Dispatched</dt>
                        <dd>{{ shipment.shipped_at ?? 'Not yet' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Delivered</dt>
                        <dd>{{ shipment.delivered_at ?? '—' }}</dd>
                    </div>
                </dl>
            </DataCard>

            <DataCard title="Items">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-2">Product</th>
                            <th class="px-4 py-2">Variant</th>
                            <th class="px-4 py-2 text-right">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="line in shipment.items"
                            :key="line.id"
                            class="border-t border-border"
                        >
                            <td class="px-4 py-2">
                                {{ line.order_item.product.name }}
                            </td>
                            <td class="px-4 py-2">
                                {{ line.order_item.variant?.sku ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-right">{{ line.quantity }}</td>
                        </tr>
                    </tbody>
                </table>
            </DataCard>

            <DataCard
                v-if="can('shipments.update') && allowedTransitions.length"
                title="Change status"
            >
                <form class="flex flex-wrap gap-3 p-4" @submit.prevent="transition">
                    <select
                        v-model="transitionForm.status"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    >
                        <option value="">Select status</option>
                        <option
                            v-for="status in allowedTransitions.filter(
                                (status) => status !== 'shipped',
                            )"
                            :key="status"
                            :value="status"
                        >
                            {{ status }}
                        </option>
                    </select>
                    <Button
                        type="submit"
                        :disabled="transitionForm.processing || !transitionForm.status"
                        >Update</Button
                    >
                    <p
                        v-for="(error, field) in transitionForm.errors"
                        :key="field"
                        class="text-sm text-red-500"
                    >
                        {{ error }}
                    </p>
                </form>
            </DataCard>
        </div>
    </AppLayout>
</template>
