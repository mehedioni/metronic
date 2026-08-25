<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { PencilIcon, Trash2Icon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Drawer } from '@/components/ui/drawer';
import { TabPanel, Tabs } from '@/components/ui/tabs';
import { usePageErrors } from '@/composables/usePageErrors';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { money, number } from '@/lib/format';
import { humanize } from '@/lib/status';
import products from '@/routes/inventory/products';

interface Variant {
    id: number;
    sku: string;
    name: string;
    cost_price: string | null;
    selling_price: string | null;
    low_stock_threshold: number;
    status: string;
}

interface InventoryItem {
    id: number;
    product_variant_id: number | null;
    quantity_on_hand: number;
    quantity_reserved: number;
}

interface Product {
    id: number;
    /** Public identifier, for links and integrations outside the app. */
    uuid: string;
    name: string;
    sku: string | null;
    description: string | null;
    type: string;
    status: string;
    cost_price: string | null;
    selling_price: string | null;
    low_stock_threshold: number;
    category: { id: number; name: string } | null;
    primary_supplier: { id: number; company_name: string } | null;
    variants: Variant[];
    suppliers: Array<{ id: number; company_name: string }>;
    inventory_items: InventoryItem[];
}

const props = defineProps<{ product: Product; options: Record<string, any> }>();

const { can } = usePermissions();
const { firstOf } = usePageErrors();

const tab = ref('overview');
const confirming = ref(false);

const onHand = computed(() =>
    props.product.inventory_items.reduce(
        (total, item) => total + item.quantity_on_hand,
        0,
    ),
);

const reserved = computed(() =>
    props.product.inventory_items.reduce(
        (total, item) => total + item.quantity_reserved,
        0,
    ),
);

const available = computed(() => onHand.value - reserved.value);

const isLow = computed(
    () => onHand.value <= props.product.low_stock_threshold,
);

/** Per-variant stock, so the variants tab can show a level per row. */
const stockByVariant = computed(() =>
    Object.fromEntries(
        props.product.inventory_items.map((item) => [
            item.product_variant_id ?? '',
            item,
        ]),
    ),
);

const tabs = computed(() => [
    { value: 'overview', label: 'Overview', count: null },
    {
        value: 'variants',
        label: 'Variants',
        count: props.product.variants.length,
    },
    {
        value: 'suppliers',
        label: 'Suppliers',
        count: props.product.suppliers.length,
    },
]);

const breadcrumbs = computed(() => [
    { label: 'Store Inventory' },
    { label: 'Products', href: products.index.url() },
    { label: props.product.name },
]);

function destroy() {
    router.delete(products.destroy.url(props.product.id));
}
</script>

<template>
    <Head :title="product.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            :title="product.name"
            :description="product.sku ?? undefined"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <StatusBadge :status="product.status" />

                <Button
                    v-if="can('products.update')"
                    variant="outline"
                    size="dense"
                    as="a"
                    :href="products.edit.url(product.id)"
                >
                    <PencilIcon />
                    Edit
                </Button>

                <Button
                    v-if="can('products.delete')"
                    variant="ghost"
                    size="dense"
                    @click="confirming = true"
                >
                    <Trash2Icon />
                    Delete
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <Tabs v-model="tab" :tabs="tabs" class="px-5">
                    <TabPanel value="overview">
                        <CardContent>
                            <dl class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs text-muted-foreground">Type</dt>
                                    <dd class="text-[0.8125rem]">
                                        {{ humanize(product.type) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Category
                                    </dt>
                                    <dd class="text-[0.8125rem]">
                                        {{ product.category?.name ?? '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Cost price
                                    </dt>
                                    <dd class="text-[0.8125rem]">
                                        {{ money(product.cost_price) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Selling price
                                    </dt>
                                    <dd class="text-[0.8125rem]">
                                        {{ money(product.selling_price) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Primary supplier
                                    </dt>
                                    <dd class="text-[0.8125rem]">
                                        {{
                                            product.primary_supplier
                                                ?.company_name ?? '—'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Low stock threshold
                                    </dt>
                                    <dd class="text-[0.8125rem]">
                                        {{ number(product.low_stock_threshold) }}
                                    </dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-xs text-muted-foreground">
                                        Public ID
                                    </dt>
                                    <dd
                                        class="truncate font-mono text-[11px] text-muted-foreground"
                                        :title="product.uuid"
                                    >
                                        {{ product.uuid }}
                                    </dd>
                                </div>
                            </dl>

                            <div v-if="product.description" class="mt-5">
                                <p class="mb-1 text-xs text-muted-foreground">
                                    Description
                                </p>
                                <p
                                    class="whitespace-pre-line text-[0.8125rem] leading-relaxed"
                                >
                                    {{ product.description }}
                                </p>
                            </div>
                        </CardContent>
                    </TabPanel>

                    <TabPanel value="variants">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead
                                    class="border-b border-border bg-muted/70 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground"
                                >
                                    <tr>
                                        <th class="px-5 py-3 text-start">SKU</th>
                                        <th class="px-5 py-3 text-start">Name</th>
                                        <th class="px-5 py-3 text-end">Price</th>
                                        <th class="px-5 py-3 text-center">On hand</th>
                                        <th class="px-5 py-3 text-center">
                                            Reserved
                                        </th>
                                        <th class="px-5 py-3 text-start">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="variant in product.variants"
                                        :key="variant.id"
                                    >
                                        <td class="px-5 py-3 font-mono">
                                            {{ variant.sku }}
                                        </td>
                                        <td class="px-5 py-3">{{ variant.name }}</td>
                                        <td class="px-5 py-3 text-end">
                                            {{ money(variant.selling_price) }}
                                        </td>
                                        <td class="px-5 py-3 text-center">
                                            {{
                                                stockByVariant[variant.id]
                                                    ?.quantity_on_hand ?? 0
                                            }}
                                        </td>
                                        <td class="px-5 py-3 text-center">
                                            {{
                                                stockByVariant[variant.id]
                                                    ?.quantity_reserved ?? 0
                                            }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <StatusBadge
                                                :status="variant.status"
                                                size="sm"
                                            />
                                        </td>
                                    </tr>
                                    <tr v-if="!product.variants.length">
                                        <td
                                            colspan="6"
                                            class="px-5 py-8 text-center text-muted-foreground"
                                        >
                                            No variants to display.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            v-if="can('products.update')"
                            class="border-t border-border px-5 py-3"
                        >
                            <Button
                                variant="outline"
                                size="dense"
                                as="a"
                                :href="products.edit.url(product.id)"
                                >Manage variants</Button
                            >
                        </div>
                    </TabPanel>

                    <TabPanel value="suppliers">
                        <ul class="divide-y divide-border">
                            <li
                                v-for="supplier in product.suppliers"
                                :key="supplier.id"
                                class="flex items-center justify-between px-5 py-3 text-[0.8125rem]"
                            >
                                <span>{{ supplier.company_name }}</span>
                                <Badge
                                    v-if="
                                        supplier.id ===
                                        product.primary_supplier?.id
                                    "
                                    variant="info"
                                    size="sm"
                                    >Primary</Badge
                                >
                            </li>
                            <li
                                v-if="!product.suppliers.length"
                                class="px-5 py-8 text-center text-muted-foreground"
                            >
                                No supplier linked to this product.
                            </li>
                        </ul>
                    </TabPanel>
                </Tabs>
            </Card>

            <Card class="self-start">
                <CardHeader>
                    <template #title>
                        <CardTitle description="Across every stockable unit"
                            >Inventory</CardTitle
                        >
                    </template>
                    <template #actions>
                        <Badge :variant="isLow ? 'warning' : 'success'">
                            {{ isLow ? 'Low stock' : 'In stock' }}
                        </Badge>
                    </template>
                </CardHeader>

                <CardContent>
                    <dl class="space-y-3">
                        <div class="flex items-baseline justify-between">
                            <dt class="text-xs text-muted-foreground">On hand</dt>
                            <dd class="text-xl font-bold">
                                {{ number(onHand) }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <dt class="text-xs text-muted-foreground">Reserved</dt>
                            <dd class="text-[0.8125rem]">
                                {{ number(reserved) }}
                            </dd>
                        </div>
                        <div
                            class="flex items-baseline justify-between border-t border-dashed border-border pt-3"
                        >
                            <dt class="text-xs text-muted-foreground">
                                Available to promise
                            </dt>
                            <dd class="text-[0.8125rem] font-semibold">
                                {{ number(available) }}
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        </div>

        <Drawer
            :open="confirming"
            title="Delete product"
            :description="`${product.name} will be removed from the catalogue.`"
            size="sm"
            @update:open="confirming = false"
        >
            <p class="text-[0.8125rem] text-muted-foreground">
                A product that already carries stock history cannot be deleted —
                the backend will refuse it and say so.
            </p>

            <p v-if="firstOf('inventory')" class="mt-3 text-[11px] text-danger">
                {{ firstOf('inventory') }}
            </p>

            <template #footer>
                <Button variant="outline" size="dense" @click="confirming = false">
                    Cancel
                </Button>
                <Button variant="destructive" size="dense" @click="destroy">
                    Delete product
                </Button>
            </template>
        </Drawer>
    </AppLayout>
</template>
