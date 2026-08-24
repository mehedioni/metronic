<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import DataCard from '@/components/DataCard.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';

interface Receipt {
    id: string;
    reference_number: string;
    source: string;
    status: string;
    received_date: string | null;
    processed_at: string | null;
    notes: string | null;
    supplier: { id: string; company_name: string } | null;
    received_by: { id: number; name: string } | null;
    items: Array<{
        id: string;
        quantity: number;
        unit_cost: string | null;
        product: { id: string; name: string; sku: string | null };
        variant: { id: string; sku: string } | null;
    }>;
}

const props = defineProps<{ receipt: Receipt; allowedTransitions: string[] }>();

const { can } = usePermissions();

const receiveForm = useForm({});
const cancelForm = useForm({ reason: '' });

function receive() {
    receiveForm.post(`/inventory/inbound/${props.receipt.id}/receive`, {
        preserveScroll: true,
    });
}

function cancel() {
    cancelForm.post(`/inventory/inbound/${props.receipt.id}/cancel`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="receipt.reference_number" />

    <AppLayout :title="receipt.reference_number">
        <div class="space-y-6">
            <DataCard title="Receipt">
                <template #actions>
                    <Button
                        v-if="can('inventory.create') && !receipt.processed_at && allowedTransitions.includes('received')"
                        :disabled="receiveForm.processing"
                        @click="receive"
                        >Receive stock</Button
                    >
                </template>

                <dl class="grid gap-2 p-4 text-sm sm:grid-cols-2">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Status</dt>
                        <dd class="capitalize">{{ receipt.status }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Source</dt>
                        <dd>{{ receipt.source }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Supplier</dt>
                        <dd>{{ receipt.supplier?.company_name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Received date</dt>
                        <dd>{{ receipt.received_date ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Posted to stock</dt>
                        <dd>{{ receipt.processed_at ?? 'Not yet' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Received by</dt>
                        <dd>{{ receipt.received_by?.name ?? '—' }}</dd>
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
                            <th class="px-4 py-2 text-right">Unit cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in receipt.items"
                            :key="item.id"
                            class="border-t border-border"
                        >
                            <td class="px-4 py-2">{{ item.product.name }}</td>
                            <td class="px-4 py-2">{{ item.variant?.sku ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">{{ item.quantity }}</td>
                            <td class="px-4 py-2 text-right">
                                {{ item.unit_cost ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </DataCard>

            <DataCard
                v-if="can('inventory.adjust') && receipt.status !== 'cancelled'"
                title="Cancel receipt"
                description="A receipt that already added stock is reversed with compensating movements."
            >
                <form class="flex flex-wrap gap-3 p-4" @submit.prevent="cancel">
                    <input
                        v-model="cancelForm.reason"
                        placeholder="Reason"
                        class="flex-1 rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <Button type="submit" variant="ghost" :disabled="cancelForm.processing"
                        >Cancel receipt</Button
                    >
                </form>
            </DataCard>
        </div>
    </AppLayout>
</template>
