<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { BanIcon, PackageCheckIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { FormField, Textarea } from '@/components/form';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Modal } from '@/components/ui/dialog';
import { usePageErrors } from '@/composables/usePageErrors';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, dateTime, money, number } from '@/lib/format';
import { humanize } from '@/lib/status';
import inbound from '@/routes/inventory/inbound';
import products from '@/routes/inventory/products';
import suppliers from '@/routes/inventory/suppliers';

interface ReceiptItem {
    id: string;
    quantity: number;
    unit_cost: string | null;
    supplier_sku: string | null;
    product: { id: string; name: string; sku: string | null };
    variant: { id: string; sku: string; name: string } | null;
}

interface Receipt {
    id: string;
    reference_number: string;
    source: string;
    status: string;
    received_date: string | null;
    notes: string | null;
    processed_at: string | null;
    cancelled_at: string | null;
    created_at: string;
    items: ReceiptItem[];
    supplier: { id: string; company_name: string } | null;
    received_by: { id: number; name: string } | null;
}

const props = defineProps<{
    receipt: Receipt;
    allowedTransitions: string[];
    options: Record<string, any>;
}>();

const { can } = usePermissions();
const { firstOf } = usePageErrors();

const receiving = ref(false);
const cancelling = ref(false);

const receiveForm = useForm({});
const cancelForm = useForm({ reason: '' });

const totalUnits = computed(() =>
    props.receipt.items.reduce((total, item) => total + item.quantity, 0),
);

const totalCost = computed(() =>
    props.receipt.items.reduce(
        (total, item) => total + Number(item.unit_cost ?? 0) * item.quantity,
        0,
    ),
);

const canReceive = computed(
    () =>
        can('inventory.create') && props.allowedTransitions.includes('received'),
);

const canCancel = computed(
    () =>
        can('inventory.adjust') && props.allowedTransitions.includes('cancelled'),
);

const timeline = computed(() =>
    [
        { label: 'Created', at: props.receipt.created_at },
        { label: 'Posted to stock', at: props.receipt.processed_at },
        { label: 'Cancelled', at: props.receipt.cancelled_at },
    ].filter((entry) => Boolean(entry.at)),
);

const breadcrumbs = computed(() => [
    { label: 'Store Inventory' },
    { label: 'Inbound Stock', href: inbound.index.url() },
    { label: props.receipt.reference_number },
]);

function receive() {
    receiveForm.post(`/inventory/inbound/${props.receipt.id}/receive`, {
        preserveScroll: true,
        onSuccess: () => (receiving.value = false),
    });
}

function cancel() {
    cancelForm.post(`/inventory/inbound/${props.receipt.id}/cancel`, {
        preserveScroll: true,
        onSuccess: () => (cancelling.value = false),
    });
}
</script>

<template>
    <Head :title="receipt.reference_number" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            :title="receipt.reference_number"
            :description="`${humanize(receipt.source)} · ${number(totalUnits)} units`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <StatusBadge :status="receipt.status" />

                <Button v-if="canReceive" size="dense" @click="receiving = true">
                    <PackageCheckIcon />
                    Post to stock
                </Button>

                <Button
                    v-if="canCancel"
                    variant="ghost"
                    size="dense"
                    @click="cancelling = true"
                >
                    <BanIcon />
                    Cancel
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <template #title>
                        <CardTitle
                            :description="`${receipt.items.length} line${receipt.items.length === 1 ? '' : 's'}`"
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
                                <th class="px-5 py-3 text-start">Supplier SKU</th>
                                <th class="px-5 py-3 text-center">Quantity</th>
                                <th class="px-5 py-3 text-end">Unit cost</th>
                                <th class="px-5 py-3 text-end">Line cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="item in receipt.items" :key="item.id">
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
                                <td class="px-5 py-3 font-mono text-muted-foreground">
                                    {{ item.supplier_sku ?? '—' }}
                                </td>
                                <td class="px-5 py-3 text-center font-medium">
                                    {{ item.quantity }}
                                </td>
                                <td class="px-5 py-3 text-end">
                                    {{ money(item.unit_cost) }}
                                </td>
                                <td class="px-5 py-3 text-end">
                                    {{
                                        item.unit_cost
                                            ? money(
                                                  Number(item.unit_cost) *
                                                      item.quantity,
                                              )
                                            : '—'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-border px-5 py-4">
                    <dl class="ms-auto max-w-xs space-y-2 text-[0.8125rem]">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Units</dt>
                            <dd>{{ number(totalUnits) }}</dd>
                        </div>
                        <div class="flex justify-between font-semibold">
                            <dt>Total cost</dt>
                            <dd>{{ totalCost ? money(totalCost) : '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </Card>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <template #title><CardTitle>Details</CardTitle></template>
                    </CardHeader>

                    <CardContent>
                        <dl class="space-y-3">
                            <div class="flex justify-between gap-3">
                                <dt class="text-xs text-muted-foreground">Supplier</dt>
                                <dd class="text-end text-[0.8125rem]">
                                    <Link
                                        v-if="receipt.supplier"
                                        :href="
                                            suppliers.show.url(receipt.supplier.id)
                                        "
                                        class="hover:underline"
                                        >{{ receipt.supplier.company_name }}</Link
                                    >
                                    <span v-else>—</span>
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-xs text-muted-foreground">Source</dt>
                                <dd class="text-[0.8125rem]">
                                    {{ humanize(receipt.source) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-xs text-muted-foreground">
                                    Received date
                                </dt>
                                <dd class="text-[0.8125rem]">
                                    {{ date(receipt.received_date) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-xs text-muted-foreground">
                                    Received by
                                </dt>
                                <dd class="text-[0.8125rem]">
                                    {{ receipt.received_by?.name ?? '—' }}
                                </dd>
                            </div>
                        </dl>

                        <div v-if="receipt.notes" class="mt-4">
                            <p class="mb-1 text-xs text-muted-foreground">Notes</p>
                            <p class="whitespace-pre-line text-[0.8125rem]">
                                {{ receipt.notes }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <template #title><CardTitle>History</CardTitle></template>
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
                                <div>
                                    <p class="text-[0.8125rem] font-medium">
                                        {{ entry.label }}
                                    </p>
                                    <p class="text-[11px] text-muted-foreground">
                                        {{ dateTime(entry.at) }}
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Modal
            :open="receiving"
            title="Post receipt to stock"
            :description="`${number(totalUnits)} units will be added to the shelf and written to the ledger.`"
            size="sm"
            @update:open="receiving = $event"
        >
            <p class="text-[0.8125rem] text-muted-foreground">
                Posting is recorded once — a repeat attempt is rejected rather
                than adding the stock twice.
            </p>

            <p v-if="firstOf('inventory', 'status')" class="mt-3 text-[11px] text-danger">
                {{ firstOf('inventory', 'status') }}
            </p>

            <template #footer>
                <Button variant="outline" size="dense" @click="receiving = false">
                    Cancel
                </Button>
                <Button
                    size="dense"
                    :disabled="receiveForm.processing"
                    @click="receive"
                >
                    Post to stock
                </Button>
            </template>
        </Modal>

        <Modal
            :open="cancelling"
            title="Cancel receipt"
            description="A posted receipt is reversed with a compensating movement, so the ledger keeps both entries."
            size="sm"
            @update:open="cancelling = $event"
        >
            <FormField label="Reason" :error="cancelForm.errors.reason">
                <Textarea v-model="cancelForm.reason" :rows="3" />
            </FormField>

            <p v-if="firstOf('inventory', 'status')" class="mt-3 text-[11px] text-danger">
                {{ firstOf('inventory', 'status') }}
            </p>

            <template #footer>
                <Button variant="outline" size="dense" @click="cancelling = false">
                    Keep receipt
                </Button>
                <Button
                    variant="destructive"
                    size="dense"
                    :disabled="cancelForm.processing"
                    @click="cancel"
                >
                    Cancel receipt
                </Button>
            </template>
        </Modal>
    </AppLayout>
</template>
