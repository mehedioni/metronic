<script setup lang="ts">
import { Deferred, Head, Link, router, useForm } from '@inertiajs/vue3';
import DataCard from '@/components/DataCard.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import customersRoutes from '@/routes/inventory/customers';

interface Customer {
    id: string;
    code: string;
    name: string;
    email: string | null;
    phone: string | null;
    address_line1: string | null;
    city: string | null;
    country: string | null;
    notes: string | null;
    status: string;
}

interface History {
    orders_count: number;
    total_spent: number;
    average_order_value: number;
    last_order_at: string | null;
    cancelled_orders_count: number;
    recent_orders: Array<{
        id: string;
        order_number: string;
        status: string;
        total: string;
        currency: string;
        items_count: number;
        created_at: string;
    }>;
}

const props = defineProps<{ customer: Customer; history?: History }>();

const { can } = usePermissions();

const form = useForm({
    name: props.customer.name,
    email: props.customer.email ?? '',
    phone: props.customer.phone ?? '',
    address_line1: props.customer.address_line1 ?? '',
    city: props.customer.city ?? '',
    country: props.customer.country ?? '',
    notes: props.customer.notes ?? '',
});

function toggleStatus() {
    router.patch(`/inventory/customers/${props.customer.id}/status`, {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="customer.name" />

    <AppLayout :title="customer.name">
        <div class="grid gap-6 lg:grid-cols-2">
            <DataCard title="Details">
                <template #actions>
                    <Button
                        v-if="can('customers.update')"
                        variant="ghost"
                        @click="toggleStatus"
                    >
                        {{ customer.status === 'active' ? 'Deactivate' : 'Activate' }}
                    </Button>
                </template>

                <dl class="space-y-2 p-4 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Code</dt>
                        <dd>{{ customer.code }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Status</dt>
                        <dd class="capitalize">{{ customer.status }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Email</dt>
                        <dd>{{ customer.email ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Phone</dt>
                        <dd>{{ customer.phone ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">City</dt>
                        <dd>{{ customer.city ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Country</dt>
                        <dd>{{ customer.country ?? '—' }}</dd>
                    </div>
                </dl>
            </DataCard>

            <DataCard v-if="can('customers.update')" title="Edit">
                <form
                    class="space-y-3 p-4"
                    @submit.prevent="form.put(customersRoutes.update.url(customer.id))"
                >
                    <input
                        v-model="form.name"
                        placeholder="Name"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.email"
                        placeholder="Email"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.phone"
                        placeholder="Phone"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.address_line1"
                        placeholder="Address"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.city"
                        placeholder="City"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.country"
                        placeholder="Country code"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        placeholder="Notes"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <Button type="submit" :disabled="form.processing">Save</Button>
                    <p
                        v-for="(error, field) in form.errors"
                        :key="field"
                        class="text-sm text-red-500"
                    >
                        {{ error }}
                    </p>
                </form>
            </DataCard>

            <DataCard title="Order history" class="lg:col-span-2">
                <Deferred data="history">
                    <template #fallback>
                        <div class="h-24 animate-pulse bg-muted/40" />
                    </template>

                    <div v-if="history" class="space-y-4 p-4 text-sm">
                        <div class="grid gap-3 sm:grid-cols-4">
                            <p>
                                Orders: <strong>{{ history.orders_count }}</strong>
                            </p>
                            <p>
                                Total spent: <strong>{{ history.total_spent }}</strong>
                            </p>
                            <p>
                                Avg. order:
                                <strong>{{ history.average_order_value }}</strong>
                            </p>
                            <p>
                                Last order:
                                <strong>{{ history.last_order_at ?? '—' }}</strong>
                            </p>
                        </div>

                        <div>
                            <h3 class="mb-2 font-semibold">Recent orders</h3>
                            <ul class="space-y-1">
                                <li
                                    v-for="order in history.recent_orders"
                                    :key="order.id"
                                >
                                    <Link
                                        :href="`/inventory/orders/${order.id}`"
                                        class="underline"
                                        >{{ order.order_number }}</Link
                                    >
                                    — <span class="capitalize">{{ order.status }}</span>
                                    — {{ order.total }} {{ order.currency }}
                                </li>
                                <li
                                    v-if="!history.recent_orders.length"
                                    class="text-muted-foreground"
                                >
                                    No orders yet.
                                </li>
                            </ul>
                        </div>
                    </div>
                </Deferred>
            </DataCard>
        </div>
    </AppLayout>
</template>
