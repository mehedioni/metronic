<script setup lang="ts">
import { Deferred, Head, router, useForm } from '@inertiajs/vue3';
import DataCard from '@/components/DataCard.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import suppliersRoutes from '@/routes/inventory/suppliers';

interface Supplier {
    id: string;
    code: string;
    company_name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
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
    }>;
}

const props = defineProps<{ supplier: Supplier; history?: History }>();

const { can } = usePermissions();

const form = useForm({
    company_name: props.supplier.company_name,
    contact_name: props.supplier.contact_name ?? '',
    email: props.supplier.email ?? '',
    phone: props.supplier.phone ?? '',
    payment_terms: props.supplier.payment_terms ?? '',
    notes: props.supplier.notes ?? '',
});

function toggleStatus() {
    router.patch(`/inventory/suppliers/${props.supplier.id}/status`, {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="supplier.company_name" />

    <AppLayout :title="supplier.company_name">
        <div class="grid gap-6 lg:grid-cols-2">
            <DataCard title="Details">
                <template #actions>
                    <Button
                        v-if="can('suppliers.update')"
                        variant="ghost"
                        @click="toggleStatus"
                    >
                        {{ supplier.status === 'active' ? 'Deactivate' : 'Activate' }}
                    </Button>
                </template>

                <dl class="space-y-2 p-4 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Code</dt>
                        <dd>{{ supplier.code }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Status</dt>
                        <dd class="capitalize">{{ supplier.status }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Contact</dt>
                        <dd>{{ supplier.contact_name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Email</dt>
                        <dd>{{ supplier.email ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Phone</dt>
                        <dd>{{ supplier.phone ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Terms</dt>
                        <dd>{{ supplier.payment_terms ?? '—' }}</dd>
                    </div>
                </dl>
            </DataCard>

            <DataCard v-if="can('suppliers.update')" title="Edit">
                <form
                    class="space-y-3 p-4"
                    @submit.prevent="
                        form.put(suppliersRoutes.update.url(supplier.id))
                    "
                >
                    <input
                        v-model="form.company_name"
                        placeholder="Company name"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.contact_name"
                        placeholder="Contact name"
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
                        v-model="form.payment_terms"
                        placeholder="Payment terms"
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

            <DataCard title="Receiving history" class="lg:col-span-2">
                <Deferred data="history">
                    <template #fallback>
                        <div class="h-24 animate-pulse bg-muted/40" />
                    </template>

                    <div v-if="history" class="space-y-4 p-4 text-sm">
                        <p>
                            Total received units:
                            <strong>{{ history.total_received_quantity }}</strong>
                        </p>
                        <p>
                            Last received:
                            <strong>{{ history.last_received_at ?? '—' }}</strong>
                        </p>

                        <div>
                            <h3 class="mb-2 font-semibold">Products supplied</h3>
                            <ul class="space-y-1">
                                <li
                                    v-for="product in history.products_supplied"
                                    :key="product.id"
                                >
                                    {{ product.name }}
                                    <span class="text-muted-foreground">{{
                                        product.sku
                                    }}</span>
                                </li>
                                <li
                                    v-if="!history.products_supplied.length"
                                    class="text-muted-foreground"
                                >
                                    No linked products.
                                </li>
                            </ul>
                        </div>

                        <div>
                            <h3 class="mb-2 font-semibold">Recent receipts</h3>
                            <ul class="space-y-1">
                                <li
                                    v-for="receipt in history.recent_receipts"
                                    :key="receipt.id"
                                >
                                    {{ receipt.reference_number }} —
                                    <span class="capitalize">{{ receipt.status }}</span>
                                </li>
                                <li
                                    v-if="!history.recent_receipts.length"
                                    class="text-muted-foreground"
                                >
                                    Nothing received yet.
                                </li>
                            </ul>
                        </div>
                    </div>
                </Deferred>
            </DataCard>
        </div>
    </AppLayout>
</template>
