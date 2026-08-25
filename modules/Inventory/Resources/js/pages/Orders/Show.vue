<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { BanIcon, CheckIcon, PackageCheckIcon, PencilIcon } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { FormField, Textarea } from '@/components/form';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Drawer } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, dateTime, money, number } from '@/lib/format';
import customers from '@/routes/inventory/customers';
import orders from '@/routes/inventory/orders';
import products from '@/routes/inventory/products';

interface OrderItem {
    id: string;
    quantity: number;
    quantity_fulfilled: number;
    unit_price: string;
    line_total: string;
    product: { id: string; name: string; sku: string | null };
    variant: { id: string; sku: string; name: string } | null;
}

interface Order {
    id: string;
    order_number: string;
    customer_name: string;
    customer_email: string | null;
    customer_phone: string | null;
    delivery_address: string | null;
    status: string;
    subtotal: string;
    discount_total: string;
    tax_total: string;
    total: string;
    currency: string;
    notes: string | null;
    confirmed_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    created_at: string;
    items: OrderItem[];
    customer: { id: string; code: string; name: string; email: string | null } | null;
    created_by: { id: number; name: string } | null;
}

const props = defineProps<{
    order: Order;
    allowedTransitions: string[];
    options: Record<string, any>;
}>();

const { can } = usePermissions();

const fulfilling = ref(false);
const cancelling = ref(false);

const confirmForm = useForm({});
const cancelForm = useForm({ reason: '' });
const fulfillForm = useForm({ lines: {} as Record<string, number> });

/** Per-line quantities, defaulted to everything still outstanding. */
const lines = reactive<Record<string, number>>(
    Object.fromEntries(
        props.order.items.map((item) => [
            item.id,
            item.quantity - item.quantity_fulfilled,
        ]),
    ),
);

const outstanding = computed(() =>
    props.order.items.reduce(
        (total, item) => total + (item.quantity - item.quantity_fulfilled),
        0,
    ),
);

const canConfirm = computed(
    () => can('orders.update') && props.allowedTransitions.includes('confirmed'),
);

const canFulfill = computed(
    () =>
        can('orders.fulfill') &&
        props.allowedTransitions.includes('completed') &&
        outstanding.value > 0,
);

const canCancel = computed(
    () => can('orders.cancel') && props.allowedTransitions.includes('cancelled'),
);

/** Lines are frozen once the order reserves stock; OrderStatus decides. */
const canEdit = computed(
    () => can('orders.update') && ['draft', 'pending'].includes(props.order.status),
);

const breadcrumbs = computed(() => [
    { label: 'Store Inventory' },
    { label: 'Orders', href: orders.index.url() },
    { label: props.order.order_number },
]);

/** The order's own history, built from the timestamps the backend records. */
const timeline = computed(() =>
    [
        { label: 'Placed', at: props.order.created_at },
        { label: 'Confirmed — stock reserved', at: props.order.confirmed_at },
        { label: 'Completed — stock issued', at: props.order.completed_at },
        { label: 'Cancelled', at: props.order.cancelled_at },
    ].filter((entry) => Boolean(entry.at)),
);

function confirm() {
    confirmForm.post(`/inventory/orders/${props.order.id}/confirm`, {
        preserveScroll: true,
    });
}

function fulfill() {
    fulfillForm.lines = Object.fromEntries(
        Object.entries(lines).filter(([, quantity]) => quantity > 0),
    );

    fulfillForm.post(`/inventory/orders/${props.order.id}/fulfill`, {
        preserveScroll: true,
        onSuccess: () => (fulfilling.value = false),
    });
}

function cancel() {
    cancelForm.post(`/inventory/orders/${props.order.id}/cancel`, {
        preserveScroll: true,
        onSuccess: () => (cancelling.value = false),
    });
}
</script>

<template>
    <Head :title="order.order_number" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            :title="order.order_number"
            :description="`Placed ${date(order.created_at)}`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <StatusBadge :status="order.status" />

                <Button
                    v-if="canEdit"
                    variant="outline"
                    size="dense"
                    as="a"
                    :href="orders.edit.url(order.id)"
                >
                    <PencilIcon />
                    Edit
                </Button>

                <Button
                    v-if="canConfirm"
                    size="dense"
                    :disabled="confirmForm.processing"
                    @click="confirm"
                >
                    <CheckIcon />
                    Confirm &amp; reserve
                </Button>

                <Button
                    v-if="canFulfill"
                    size="dense"
                    variant="outline"
                    @click="fulfilling = true"
                >
                    <PackageCheckIcon />
                    Fulfil
                </Button>

                <Button
                    v-if="canCancel"
                    size="dense"
                    variant="ghost"
                    @click="cancelling = true"
                >
                    <BanIcon />
                    Cancel
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <template #title>
                            <CardTitle
                                :description="`${order.items.length} line${order.items.length === 1 ? '' : 's'}, ${number(outstanding)} units outstanding`"
                                >Lines</CardTitle
                            >
                        </template>
                    </CardHeader>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead
                                class="border-b border-border bg-muted/70 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-5 py-3 text-start">Product</th>
                                    <th class="px-5 py-3 text-center">Ordered</th>
                                    <th class="px-5 py-3 text-center">Fulfilled</th>
                                    <th class="px-5 py-3 text-end">Unit price</th>
                                    <th class="px-5 py-3 text-end">Line total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="item in order.items" :key="item.id">
                                    <td class="px-5 py-3">
                                        <Link
                                            :href="products.show.url(item.product.id)"
                                            class="font-medium hover:underline"
                                            >{{ item.product.name }}</Link
                                        >
                                        <span
                                            class="block font-mono text-[11px] text-muted-foreground"
                                        >
                                            {{
                                                item.variant?.sku ??
                                                item.product.sku ??
                                                '—'
                                            }}
                                            <template v-if="item.variant">
                                                · {{ item.variant.name }}</template
                                            >
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        {{ item.quantity }}
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <Badge
                                            :variant="
                                                item.quantity_fulfilled >=
                                                item.quantity
                                                    ? 'success'
                                                    : item.quantity_fulfilled > 0
                                                      ? 'warning'
                                                      : 'neutral'
                                            "
                                            size="sm"
                                        >
                                            {{ item.quantity_fulfilled }} /
                                            {{ item.quantity }}
                                        </Badge>
                                    </td>
                                    <td class="px-5 py-3 text-end">
                                        {{ money(item.unit_price, order.currency) }}
                                    </td>
                                    <td class="px-5 py-3 text-end font-medium">
                                        {{ money(item.line_total, order.currency) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-border px-5 py-4">
                        <dl class="ms-auto max-w-xs space-y-2 text-[0.8125rem]">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Subtotal</dt>
                                <dd>{{ money(order.subtotal, order.currency) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Discount</dt>
                                <dd>
                                    {{ money(order.discount_total, order.currency) }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Tax</dt>
                                <dd>{{ money(order.tax_total, order.currency) }}</dd>
                            </div>
                            <div
                                class="flex justify-between border-t border-dashed border-border pt-2 font-semibold"
                            >
                                <dt>Total</dt>
                                <dd>{{ money(order.total, order.currency) }}</dd>
                            </div>
                        </dl>
                    </div>
                </Card>

                <Card v-if="order.notes">
                    <CardHeader>
                        <template #title><CardTitle>Notes</CardTitle></template>
                    </CardHeader>
                    <CardContent>
                        <p class="whitespace-pre-line text-[0.8125rem]">
                            {{ order.notes }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <template #title><CardTitle>Customer</CardTitle></template>
                    </CardHeader>

                    <CardContent>
                        <p class="text-[0.8125rem] font-medium">
                            <Link
                                v-if="order.customer"
                                :href="customers.show.url(order.customer.id)"
                                class="hover:underline"
                                >{{ order.customer_name }}</Link
                            >
                            <span v-else>{{ order.customer_name }}</span>
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            {{
                                order.customer
                                    ? order.customer.code
                                    : 'Walk-in — no customer record'
                            }}
                        </p>

                        <dl class="mt-4 space-y-3">
                            <div class="flex justify-between gap-3">
                                <dt class="text-xs text-muted-foreground">Email</dt>
                                <dd class="truncate text-[0.8125rem]">
                                    {{ order.customer_email ?? '—' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-xs text-muted-foreground">Phone</dt>
                                <dd class="text-[0.8125rem]">
                                    {{ order.customer_phone ?? '—' }}
                                </dd>
                            </div>
                            <div v-if="order.delivery_address">
                                <dt class="text-xs text-muted-foreground">
                                    Delivery address
                                </dt>
                                <dd class="mt-1 whitespace-pre-line text-[0.8125rem]">
                                    {{ order.delivery_address }}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <template #title>
                            <CardTitle
                                description="Recorded by the order's own timestamps"
                                >History</CardTitle
                            >
                        </template>
                    </CardHeader>

                    <CardContent>
                        <ol class="space-y-4">
                            <li
                                v-for="entry in timeline"
                                :key="entry.label"
                                class="flex gap-3"
                            >
                                <span
                                    class="mt-1 size-2 shrink-0 rounded-full bg-primary"
                                />
                                <div class="min-w-0">
                                    <p class="text-[0.8125rem] font-medium">
                                        {{ entry.label }}
                                    </p>
                                    <p class="text-[11px] text-muted-foreground">
                                        {{ dateTime(entry.at) }}
                                    </p>
                                </div>
                            </li>
                        </ol>

                        <p
                            v-if="order.created_by"
                            class="mt-4 border-t border-dashed border-border pt-3 text-[11px] text-muted-foreground"
                        >
                            Created by {{ order.created_by.name }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Drawer
            :open="fulfilling"
            title="Fulfil order"
            description="Deducts on-hand stock for the quantities handed over and releases their reservation. Leaving the full amounts completes the order."
            @update:open="fulfilling = $event"
        >
            <div class="space-y-3">
                <div
                    v-for="item in order.items"
                    :key="item.id"
                    class="flex items-center gap-3"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[0.8125rem] font-medium">
                            {{ item.product.name }}
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            {{ item.quantity - item.quantity_fulfilled }} outstanding
                        </p>
                    </div>

                    <Input
                        v-model.number="lines[item.id]"
                        type="number"
                        min="0"
                        :max="item.quantity - item.quantity_fulfilled"
                        class="w-24"
                    />
                </div>

                <p
                    v-for="(error, field) in fulfillForm.errors"
                    :key="field"
                    class="text-[11px] text-danger"
                >
                    {{ error }}
                </p>
            </div>

            <template #footer>
                <Button variant="outline" size="dense" @click="fulfilling = false">
                    Cancel
                </Button>
                <Button
                    size="dense"
                    :disabled="fulfillForm.processing"
                    @click="fulfill"
                >
                    Fulfil
                </Button>
            </template>
        </Drawer>

        <Drawer
            :open="cancelling"
            title="Cancel order"
            description="Releases outstanding reservations and returns any fulfilled units to stock as a customer return."
            size="sm"
            @update:open="cancelling = $event"
        >
            <FormField label="Reason" :error="cancelForm.errors.reason">
                <Textarea
                    v-model="cancelForm.reason"
                    :rows="3"
                    placeholder="Recorded on the order and on every compensating movement"
                />
            </FormField>

            <template #footer>
                <Button variant="outline" size="dense" @click="cancelling = false">
                    Keep order
                </Button>
                <Button
                    variant="destructive"
                    size="dense"
                    :disabled="cancelForm.processing"
                    @click="cancel"
                >
                    Cancel order
                </Button>
            </template>
        </Drawer>
    </AppLayout>
</template>
