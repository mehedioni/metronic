<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import DataCard from '@/components/DataCard.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import stockRoutes from '@/routes/inventory/stock';
import type { Paginated } from '@/types';

interface StockItem {
    id: string;
    quantity_on_hand: number;
    quantity_reserved: number;
    product: {
        id: string;
        name: string;
        sku: string | null;
        low_stock_threshold: number;
    };
    variant: { id: string; sku: string; name: string } | null;
}

const props = defineProps<{
    items: Paginated<StockItem>;
    filters: Record<string, unknown>;
    categories: Array<{ id: string; name: string }>;
    movementTypes: string[];
}>();

const { can } = usePermissions();

const filters = reactive({
    search: (props.filters.search as string) ?? '',
    category_id: (props.filters.category_id as string) ?? '',
    low_stock: Boolean(props.filters.low_stock),
});

watch(filters, (value) => {
    router.get(stockRoutes.index.url(), { ...value }, {
        preserveState: true,
        replace: true,
    });
});

/** Units that may still be promised to a new order. */
function available(item: StockItem): number {
    return item.quantity_on_hand - item.quantity_reserved;
}

const adjustable = computed(() =>
    props.items.data.map((item) => ({
        label: item.variant
            ? `${item.product.name} · ${item.variant.sku}`
            : item.product.name,
        productId: item.product.id,
        variantId: item.variant?.id ?? '',
    })),
);

const form = useForm({
    product_id: '',
    product_variant_id: '',
    type: 'adjustment_increase',
    quantity: 1,
    reason: '',
});

function pickUnit(index: string) {
    const unit = adjustable.value[Number(index)];

    if (!unit) {
        return;
    }

    form.product_id = unit.productId;
    form.product_variant_id = unit.variantId;
}

function adjust() {
    form.post(stockRoutes.adjust.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset('quantity', 'reason'),
    });
}
</script>

<template>
    <Head title="Stock on hand" />

    <AppLayout title="Stock on hand">
        <div class="space-y-6">
            <DataCard title="Filters">
                <div class="flex flex-wrap gap-3 p-4">
                    <input
                        v-model="filters.search"
                        placeholder="Search product or SKU"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <select
                        v-model="filters.category_id"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    >
                        <option value="">All categories</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="filters.low_stock" type="checkbox" />
                        Low stock only
                    </label>
                </div>
            </DataCard>

            <DataCard
                v-if="can('inventory.adjust')"
                title="Adjust stock"
                description="Every adjustment writes a stock movement; a reason is required when stock is removed."
            >
                <form class="grid gap-3 p-4 sm:grid-cols-2" @submit.prevent="adjust">
                    <select
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                        @change="pickUnit(($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">Select a stock item</option>
                        <option
                            v-for="(unit, index) in adjustable"
                            :key="unit.productId + unit.variantId"
                            :value="index"
                        >
                            {{ unit.label }}
                        </option>
                    </select>

                    <select
                        v-model="form.type"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    >
                        <option v-for="type in movementTypes" :key="type" :value="type">
                            {{ type }}
                        </option>
                    </select>

                    <input
                        v-model.number="form.quantity"
                        type="number"
                        min="1"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.reason"
                        placeholder="Reason"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    />

                    <div class="sm:col-span-2">
                        <Button
                            type="submit"
                            :disabled="form.processing || !form.product_id"
                            >Apply adjustment</Button
                        >
                        <p
                            v-for="(error, field) in form.errors"
                            :key="field"
                            class="text-sm text-red-500"
                        >
                            {{ error }}
                        </p>
                    </div>
                </form>
            </DataCard>

            <DataCard title="Stock on hand">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-4 py-2">Product</th>
                                <th class="px-4 py-2">SKU</th>
                                <th class="px-4 py-2">Variant</th>
                                <th class="px-4 py-2 text-right">On hand</th>
                                <th class="px-4 py-2 text-right">Reserved</th>
                                <th class="px-4 py-2 text-right">Available</th>
                                <th class="px-4 py-2 text-right">Threshold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in items.data"
                                :key="item.id"
                                class="border-t border-border"
                            >
                                <td class="px-4 py-2">{{ item.product.name }}</td>
                                <td class="px-4 py-2 text-muted-foreground">
                                    {{ item.product.sku ?? '—' }}
                                </td>
                                <td class="px-4 py-2">{{ item.variant?.sku ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    {{ item.quantity_on_hand }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ item.quantity_reserved }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ available(item) }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ item.product.low_stock_threshold }}
                                </td>
                            </tr>
                            <tr v-if="!items.data.length">
                                <td class="px-4 py-3 text-muted-foreground" colspan="7">
                                    No stock records yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    :links="items.links"
                    :from="items.from"
                    :to="items.to"
                    :total="items.total"
                />
            </DataCard>
        </div>
    </AppLayout>
</template>
