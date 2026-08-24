<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import DataCard from '@/components/DataCard.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';

interface OrderItem {
    id: string;
    quantity: number;
    quantity_shipped: number;
    unit_price: string;
    line_total: string;
    product: { id: string; name: string; sku: string | null };
    variant: { id: string; sku: string } | null;
}

interface Order {
    id: string;
    order_number: string;
    customer_name: string;
    customer_email: string | null;
    status: string;
    subtotal: string;
    total: string;
    currency: string;
    confirmed_at: string | null;
    items: OrderItem[];
    shipments: Array<{
        id: string;
        shipment_number: string;
        status: string;
        shipped_at: string | null;
    }>;
}

const props = defineProps<{ order: Order; allowedTransitions: string[] }>();

const { can } = usePermissions();

const confirmForm = useForm({});
const cancelForm = useForm({ reason: '' });

const shipmentLines = reactive<Record<string, number>>(
    Object.fromEntries(
        props.order.items.map((item) => [
            item.id,
            item.quantity - item.quantity_shipped,
        ]),
    ),
);

const shipmentForm = useForm({
    carrier: '',
    tracking_number: '',
    items: [] as Array<{ order_item_id: string; quantity: number }>,
});

function confirm() {
    confirmForm.post(`/inventory/orders/${props.order.id}/confirm`, {
        preserveScroll: true,
    });
}

function cancel() {
    cancelForm.post(`/inventory/orders/${props.order.id}/cancel`, {
        preserveScroll: true,
    });
}

function createShipment() {
    shipmentForm.items = Object.entries(shipmentLines)
        .filter(([, quantity]) => quantity > 0)
        .map(([orderItemId, quantity]) => ({
            order_item_id: orderItemId,
            quantity,
        }));

    shipmentForm.post(`/inventory/orders/${props.order.id}/shipments`);
}
</script>

<template>
    <Head :title="order.order_number" />

    <AppLayout :title="order.order_number">
        <div class="space-y-6">
            <DataCard title="Order">
                <template #actions>
                    <Button
                        v-if="can('orders.update') && allowedTransitions.includes('confirmed')"
                        :disabled="confirmForm.processing"
                        @click="confirm"
                        >Confirm &amp; reserve stock</Button
                    >
                </template>

                <dl class="grid gap-2 p-4 text-sm sm:grid-cols-2">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Status</dt>
                        <dd class="capitalize">{{ order.status }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Customer</dt>
                        <dd>{{ order.customer_name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Confirmed</dt>
                        <dd>{{ order.confirmed_at ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Total</dt>
                        <dd>{{ order.total }} {{ order.currency }}</dd>
                    </div>
                </dl>
            </DataCard>

            <DataCard title="Lines">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-2">Product</th>
                            <th class="px-4 py-2">Variant</th>
                            <th class="px-4 py-2 text-right">Ordered</th>
                            <th class="px-4 py-2 text-right">Shipped</th>
                            <th class="px-4 py-2 text-right">Unit price</th>
                            <th class="px-4 py-2 text-right">Line total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in order.items"
                            :key="item.id"
                            class="border-t border-border"
                        >
                            <td class="px-4 py-2">{{ item.product.name }}</td>
                            <td class="px-4 py-2">{{ item.variant?.sku ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">{{ item.quantity }}</td>
                            <td class="px-4 py-2 text-right">
                                {{ item.quantity_shipped }}
                            </td>
                            <td class="px-4 py-2 text-right">{{ item.unit_price }}</td>
                            <td class="px-4 py-2 text-right">{{ item.line_total }}</td>
                        </tr>
                    </tbody>
                </table>
            </DataCard>

            <DataCard title="Shipments">
                <ul class="space-y-1 p-4 text-sm">
                    <li v-for="shipment in order.shipments" :key="shipment.id">
                        <Link
                            :href="`/inventory/shipments/${shipment.id}`"
                            class="underline"
                            >{{ shipment.shipment_number }}</Link
                        >
                        — <span class="capitalize">{{ shipment.status }}</span>
                    </li>
                    <li v-if="!order.shipments.length" class="text-muted-foreground">
                        No shipments yet.
                    </li>
                </ul>
            </DataCard>

            <DataCard
                v-if="can('shipments.create') && order.status !== 'cancelled'"
                title="New shipment"
                description="Creating a shipment does not move stock; dispatching it does."
            >
                <form class="space-y-3 p-4" @submit.prevent="createShipment">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input
                            v-model="shipmentForm.carrier"
                            placeholder="Carrier"
                            class="rounded border border-border bg-background px-3 py-2 text-sm"
                        />
                        <input
                            v-model="shipmentForm.tracking_number"
                            placeholder="Tracking number"
                            class="rounded border border-border bg-background px-3 py-2 text-sm"
                        />
                    </div>

                    <div
                        v-for="item in order.items"
                        :key="item.id"
                        class="flex items-center gap-3 text-sm"
                    >
                        <span class="flex-1">{{ item.product.name }}</span>
                        <input
                            v-model.number="shipmentLines[item.id]"
                            type="number"
                            min="0"
                            :max="item.quantity - item.quantity_shipped"
                            class="w-24 rounded border border-border bg-background px-3 py-2"
                        />
                    </div>

                    <Button type="submit" :disabled="shipmentForm.processing"
                        >Create shipment</Button
                    >
                    <p
                        v-for="(error, field) in shipmentForm.errors"
                        :key="field"
                        class="text-sm text-red-500"
                    >
                        {{ error }}
                    </p>
                </form>
            </DataCard>

            <DataCard
                v-if="can('orders.cancel') && order.status !== 'cancelled'"
                title="Cancel order"
                description="Releases outstanding reservations and returns any shipped units to stock."
            >
                <form class="flex flex-wrap gap-3 p-4" @submit.prevent="cancel">
                    <input
                        v-model="cancelForm.reason"
                        placeholder="Reason"
                        class="flex-1 rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <Button type="submit" variant="ghost" :disabled="cancelForm.processing"
                        >Cancel order</Button
                    >
                </form>
            </DataCard>
        </div>
    </AppLayout>
</template>
