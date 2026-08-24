<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import DataCard from '@/components/DataCard.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import productsRoutes from '@/routes/inventory/products';

interface Variant {
    id: string;
    sku: string;
    name: string;
    status: string;
    selling_price: string | null;
}

interface Product {
    id: string;
    name: string;
    sku: string | null;
    description: string | null;
    type: string;
    status: string;
    cost_price: string | null;
    selling_price: string | null;
    low_stock_threshold: number;
    category: { id: string; name: string } | null;
    primary_supplier: { id: string; company_name: string } | null;
    variants: Variant[];
    inventory_items: Array<{
        id: string;
        product_variant_id: string | null;
        quantity_on_hand: number;
        quantity_reserved: number;
    }>;
}

const props = defineProps<{
    product: Product;
    options: {
        categories: Array<{ id: string; name: string }>;
        suppliers: Array<{ id: string; company_name: string }>;
        statuses: string[];
    };
}>();

const { can } = usePermissions();

const form = useForm({
    name: props.product.name,
    sku: props.product.sku ?? '',
    description: props.product.description ?? '',
    category_id: props.product.category?.id ?? '',
    primary_supplier_id: props.product.primary_supplier?.id ?? '',
    status: props.product.status,
    cost_price: props.product.cost_price ?? '',
    selling_price: props.product.selling_price ?? '',
    low_stock_threshold: props.product.low_stock_threshold,
});

const variantForm = useForm({
    variants: props.product.variants.map((variant) => ({
        id: variant.id,
        sku: variant.sku,
        name: variant.name,
        status: variant.status,
    })),
});

function addVariant() {
    variantForm.variants.push({ id: '', sku: '', name: '', status: 'active' });
}

function saveVariants() {
    variantForm
        .transform((data) => ({
            variants: data.variants.map((variant) => ({
                ...variant,
                id: variant.id || undefined,
            })),
        }))
        .put(productsRoutes.update.url(props.product.id), {
            preserveScroll: true,
        });
}
</script>

<template>
    <Head :title="product.name" />

    <AppLayout :title="product.name">
        <div class="grid gap-6 lg:grid-cols-2">
            <DataCard title="Details">
                <dl class="space-y-2 p-4 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">SKU</dt>
                        <dd>{{ product.sku ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Type</dt>
                        <dd class="capitalize">{{ product.type }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Status</dt>
                        <dd class="capitalize">{{ product.status }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Category</dt>
                        <dd>{{ product.category?.name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Primary supplier</dt>
                        <dd>{{ product.primary_supplier?.company_name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">On hand</dt>
                        <dd>
                            {{
                                product.inventory_items.reduce(
                                    (total, item) => total + item.quantity_on_hand,
                                    0,
                                )
                            }}
                        </dd>
                    </div>
                </dl>
            </DataCard>

            <DataCard v-if="can('products.update')" title="Edit">
                <form
                    class="space-y-3 p-4"
                    @submit.prevent="form.put(productsRoutes.update.url(product.id))"
                >
                    <input
                        v-model="form.name"
                        placeholder="Name"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.sku"
                        placeholder="SKU"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <select
                        v-model="form.category_id"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    >
                        <option value="">No category</option>
                        <option
                            v-for="category in options.categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <select
                        v-model="form.primary_supplier_id"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    >
                        <option value="">No primary supplier</option>
                        <option
                            v-for="supplier in options.suppliers"
                            :key="supplier.id"
                            :value="supplier.id"
                        >
                            {{ supplier.company_name }}
                        </option>
                    </select>
                    <select
                        v-model="form.status"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    >
                        <option
                            v-for="status in options.statuses"
                            :key="status"
                            :value="status"
                        >
                            {{ status }}
                        </option>
                    </select>
                    <input
                        v-model.number="form.low_stock_threshold"
                        type="number"
                        min="0"
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

            <DataCard title="Variants" class="lg:col-span-2">
                <template #actions>
                    <Button v-if="can('products.update')" variant="ghost" @click="addVariant"
                        >Add variant</Button
                    >
                </template>

                <div class="space-y-3 p-4">
                    <div
                        v-for="(variant, index) in variantForm.variants"
                        :key="index"
                        class="grid gap-2 sm:grid-cols-3"
                    >
                        <input
                            v-model="variant.sku"
                            placeholder="SKU"
                            class="rounded border border-border bg-background px-3 py-2 text-sm"
                        />
                        <input
                            v-model="variant.name"
                            placeholder="Variant name"
                            class="rounded border border-border bg-background px-3 py-2 text-sm"
                        />
                        <select
                            v-model="variant.status"
                            class="rounded border border-border bg-background px-3 py-2 text-sm"
                        >
                            <option value="active">active</option>
                            <option value="inactive">inactive</option>
                        </select>
                    </div>

                    <p v-if="!variantForm.variants.length" class="text-muted-foreground">
                        This product has no variants.
                    </p>

                    <Button
                        v-if="can('products.update')"
                        :disabled="variantForm.processing"
                        @click="saveVariants"
                        >Save variants</Button
                    >
                </div>
            </DataCard>
        </div>
    </AppLayout>
</template>
