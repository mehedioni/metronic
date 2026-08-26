<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { PlusIcon, Trash2Icon } from 'lucide-vue-next';
import { computed } from 'vue';
import { FormField, FormSection, Textarea } from '@/components/form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import ProductImageDropzone from './ProductImageDropzone.vue';
import ProductImageManager from './ProductImageManager.vue';
import type {ProductImage} from './ProductImageManager.vue';

interface Option {
    id: number;
    name?: string;
    company_name?: string;
}

export interface VariantRow {
    /** Present on a variant that already exists; absent on a new one. */
    id?: number;
    sku: string;
    name: string;
    cost_price: string | number | null;
    selling_price: string | number | null;
    low_stock_threshold: number;
    status: string;
}

export interface ProductPayload {
    name: string;
    sku: string;
    description: string;
    category_id: string;
    primary_supplier_id: string;
    type: string;
    status: string;
    cost_price: string | number | null;
    selling_price: string | number | null;
    low_stock_threshold: number;
    variants: VariantRow[];
    /** Chosen in the form, uploaded with it; empty when editing. */
    images: File[];
}

/**
 * Shared create/edit form. Only fields the backend actually persists appear
 * here — a control with nothing behind it is worse than no control.
 */
const props = defineProps<{
    /** Existing values when editing; omitted when creating. */
    product?: Partial<ProductPayload> & { id?: number | string; variants?: VariantRow[]; images?: ProductImage[] };
    options: {
        categories?: Option[];
        suppliers?: Option[];
        types?: string[];
        statuses?: string[];
    };
    /** Where to submit, and with which verb. */
    action: string;
    method?: 'post' | 'put';
    submitLabel?: string;
    /** Omit inside a drawer and listen for @cancel instead. */
    cancelHref?: string;
}>();

const emit = defineEmits<{
    cancel: [];
}>();

const form = useForm<ProductPayload>({
    name: props.product?.name ?? '',
    sku: props.product?.sku ?? '',
    description: props.product?.description ?? '',
    category_id:
        props.product?.category_id !== undefined &&
        props.product?.category_id !== null
            ? String(props.product.category_id)
            : '',
    primary_supplier_id:
        props.product?.primary_supplier_id !== undefined &&
        props.product?.primary_supplier_id !== null
            ? String(props.product.primary_supplier_id)
            : '',
    type: props.product?.type ?? 'simple',
    status: props.product?.status ?? 'active',
    cost_price: props.product?.cost_price ?? '',
    selling_price: props.product?.selling_price ?? '',
    low_stock_threshold: props.product?.low_stock_threshold ?? 0,
    variants: (props.product?.variants ?? []).map((variant) => ({ ...variant })),
    images: [],
});

/**
 * A new product has no id to upload against, so its images travel with the
 * create request. An existing one uses ProductImageManager on the edit screen,
 * which can reorder and promote what is already stored.
 */
const isCreate = computed(() => !props.product?.id);

/** Validation reports a bad upload as `images.0`, so match the whole family. */
const imageErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([field]) => field === 'images' || field.startsWith('images.'))
        .map(([, message]) => message as string),
);

/** A variable product must ship at least one variant — the backend rejects it otherwise. */
const isVariable = computed(() => form.type === 'variable');

function addVariant() {
    form.variants.push({
        sku: '',
        name: '',
        cost_price: '',
        selling_price: '',
        low_stock_threshold: 0,
        status: 'active',
    });
}

function removeVariant(index: number) {
    form.variants.splice(index, 1);
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('cancel');
        },
    };

    if ((props.method ?? 'post') === 'put') {
        // Editing never carries files; images are managed on their own endpoints.
        form
            .transform((data) =>
                Object.fromEntries(
                    Object.entries(data).filter(([field]) => field !== 'images'),
                ),
            )
            .put(props.action, options);

        return;
    }

    form.post(props.action, options);
}

defineExpose({ form });
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                <FormSection title="Basic info">
                    <FormField label="Product name" :error="form.errors.name" required>
                        <Input
                            v-model="form.name"
                            placeholder="Product name"
                            :invalid="Boolean(form.errors.name)"
                        />
                    </FormField>

                    <FormField label="SKU" :error="form.errors.sku" hint="Left blank, the product has no SKU of its own.">
                        <Input
                            v-model="form.sku"
                            placeholder="SKU"
                            class="font-mono"
                            :invalid="Boolean(form.errors.sku)"
                        />
                    </FormField>

                    <FormField label="Description" :error="form.errors.description">
                        <Textarea
                            v-model="form.description"
                            :rows="4"
                            placeholder="Product description"
                            :invalid="Boolean(form.errors.description)"
                        />
                    </FormField>
                </FormSection>

                <FormSection
                    title="Variants"
                    :description="
                        isVariable
                            ? 'A variable product needs at least one variant.'
                            : 'Variants are optional for a simple product.'
                    "
                >
                    <template #actions>
                        <Button
                            type="button"
                            variant="outline"
                            size="dense"
                            @click="addVariant"
                        >
                            <PlusIcon />
                            Add variant
                        </Button>
                    </template>

                    <p
                        v-if="!form.variants.length"
                        class="py-4 text-center text-xs text-muted-foreground"
                    >
                        No variants to display.
                    </p>

                    <p v-if="form.errors.variants" class="text-2xs text-danger">
                        {{ form.errors.variants }}
                    </p>

                    <div
                        v-for="(variant, index) in form.variants"
                        :key="variant.id ?? index"
                        class="rounded-md border border-border p-4"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <Badge variant="outline" size="sm">
                                Variant {{ index + 1 }}
                            </Badge>

                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-dense"
                                aria-label="Remove variant"
                                @click="removeVariant(index)"
                            >
                                <Trash2Icon />
                            </Button>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="SKU"
                                :error="form.errors[`variants.${index}.sku`]"
                                required
                            >
                                <Input v-model="variant.sku" class="font-mono" />
                            </FormField>

                            <FormField
                                label="Name"
                                :error="form.errors[`variants.${index}.name`]"
                                required
                            >
                                <Input v-model="variant.name" placeholder="M / Black" />
                            </FormField>

                            <FormField label="Cost price">
                                <Input v-model="variant.cost_price" type="number" />
                            </FormField>

                            <FormField label="Selling price">
                                <Input v-model="variant.selling_price" type="number" />
                            </FormField>

                            <FormField label="Low stock threshold">
                                <Input
                                    v-model="variant.low_stock_threshold"
                                    type="number"
                                />
                            </FormField>

                            <FormField label="Status">
                                <Select v-model="variant.status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </Select>
                            </FormField>
                        </div>
                    </div>
                </FormSection>
            </div>

            <div class="space-y-5">
                <FormSection
                    title="Images"
                    :description="
                        isCreate
                            ? 'The first image represents the product in lists.'
                            : 'Upload, reorder or remove images. The first is the primary.'
                    "
                >
                    <ProductImageDropzone v-if="isCreate" v-model="form.images" />

                    <ProductImageManager
                        v-else
                        :product-id="Number(props.product?.id)"
                        :images="props.product?.images ?? []"
                        :editable="true"
                    />

                    <p
                        v-for="message in imageErrors"
                        :key="message"
                        class="text-2xs text-danger"
                    >
                        {{ message }}
                    </p>
                </FormSection>

                <FormSection title="Classification">
                    <FormField label="Category" :error="form.errors.category_id">
                        <Select v-model="form.category_id">
                            <option value="">No category</option>
                            <option
                                v-for="category in options.categories ?? []"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </option>
                        </Select>
                    </FormField>

                    <FormField
                        label="Primary supplier"
                        :error="form.errors.primary_supplier_id"
                    >
                        <Select v-model="form.primary_supplier_id">
                            <option value="">No supplier</option>
                            <option
                                v-for="supplier in options.suppliers ?? []"
                                :key="supplier.id"
                                :value="supplier.id"
                            >
                                {{ supplier.company_name }}
                            </option>
                        </Select>
                    </FormField>

                    <FormField label="Type" :error="form.errors.type">
                        <Select v-model="form.type">
                            <option
                                v-for="type in options.types ?? []"
                                :key="type"
                                :value="type"
                            >
                                {{ type }}
                            </option>
                        </Select>
                    </FormField>

                    <FormField label="Status" :error="form.errors.status">
                        <Select v-model="form.status">
                            <option
                                v-for="status in options.statuses ?? []"
                                :key="status"
                                :value="status"
                            >
                                {{ status }}
                            </option>
                        </Select>
                    </FormField>
                </FormSection>

                <FormSection title="Pricing">
                    <FormField label="Cost price" :error="form.errors.cost_price">
                        <Input v-model="form.cost_price" type="number" step="0.01" />
                    </FormField>

                    <FormField
                        label="Selling price"
                        :error="form.errors.selling_price"
                    >
                        <Input
                            v-model="form.selling_price"
                            type="number"
                            step="0.01"
                        />
                    </FormField>
                </FormSection>

                <FormSection title="Inventory">
                    <FormField
                        label="Low stock threshold"
                        :error="form.errors.low_stock_threshold"
                        hint="The planner treats this as the level to restock back to."
                    >
                        <Input
                            v-model="form.low_stock_threshold"
                            type="number"
                            min="0"
                        />
                    </FormField>
                </FormSection>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <Button
                v-if="cancelHref"
                variant="outline"
                size="dense"
                as="a"
                :href="cancelHref"
            >
                Cancel
            </Button>
            <Button
                v-else
                type="button"
                variant="outline"
                size="dense"
                @click="emit('cancel')"
            >
                Cancel
            </Button>
            <Button type="submit" size="dense" :disabled="form.processing">
                {{ submitLabel ?? 'Save' }}
            </Button>
        </div>
    </form>
</template>
