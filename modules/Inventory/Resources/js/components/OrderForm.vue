<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { PlusIcon, TriangleAlertIcon, Trash2Icon } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { FormField, FormSection, Textarea } from '@/components/form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { money } from '@/lib/format';

interface StatusOption {
    id: number;
    key: string;
    label: string;
    variant: string;
}

interface CustomerOption {
    id: number;
    code: string;
    name: string;
    email: string | null;
}

interface VariantOption {
    id: number;
    sku: string;
    name: string;
    selling_price: string | null;
}

interface StockRow {
    product_id: number;
    product_variant_id: number | null;
    quantity_on_hand: number;
    quantity_reserved: number;
}

interface ProductOption {
    id: number;
    name: string;
    sku: string | null;
    type: string;
    selling_price: string | null;
    variants: VariantOption[];
    inventory_items: StockRow[];
}

export interface OrderLine {
    product_id: string;
    product_variant_id: string;
    quantity: number;
    unit_price: string | number | '';
}

export interface OrderPayload {
    customer_id: string;
    customer_name: string;
    customer_email: string;
    customer_phone: string;
    delivery_address: string;
    status_id: number | '';
    currency: string;
    discount_total: string | number | '';
    tax_total: string | number | '';
    notes: string;
    items: OrderLine[];
}

/**
 * Shared create/edit form for an order.
 *
 * Money is only ever computed here for display — the backend recalculates
 * every total from the saved lines (Order::recalculateTotals), so what the
 * form shows can never become the source of truth.
 */
const props = defineProps<{
    order?: Partial<OrderPayload> & {
        id?: string;
        items?: Array<{
            product_id: number;
            product_variant_id: number | null;
            quantity: number;
            unit_price: string;
        }>;
    };
    options: {
        customers?: CustomerOption[];
        products?: ProductOption[];
        /** The whole configured lifecycle, for reference. */
        statuses?: StatusOption[];
        /** The subset a form may set — see config/orders.php. */
        assignableStatuses?: StatusOption[];
    };
    action: string;
    method?: 'post' | 'put';
    /** Screens that fix the status themselves (quotes) hide the field. */
    hideStatus?: boolean;
    submitLabel?: string;
    cancelHref?: string;
}>();

defineEmits<{
    cancel: [];
}>();

const form = useForm<OrderPayload>({
    customer_id: props.order?.customer_id ?? '',
    customer_name: props.order?.customer_name ?? '',
    customer_email: props.order?.customer_email ?? '',
    customer_phone: props.order?.customer_phone ?? '',
    delivery_address: props.order?.delivery_address ?? '',
    // A new order starts as a draft: nothing is reserved until it is confirmed.
    status_id: props.order?.status_id ?? '',
    currency: props.order?.currency ?? 'USD',
    discount_total: props.order?.discount_total ?? '',
    tax_total: props.order?.tax_total ?? '',
    notes: props.order?.notes ?? '',
    items: (props.order?.items ?? []).map((item) => ({
        // Line ids are held as strings: that is what the selects bind to, and
        // Laravel's "integer" rule accepts a numeric string on the way back.
        product_id: String(item.product_id),
        product_variant_id:
            item.product_variant_id === null
                ? ''
                : String(item.product_variant_id),
        quantity: item.quantity,
        unit_price: item.unit_price,
    })),
});

/**
 * Only the statuses config marks assignable are offered. Everything else is
 * reached through an action that carries the stock effect with it.
 */
const selectableStatuses = computed(() => props.options.assignableStatuses ?? []);

const products = computed(() => props.options.products ?? []);

function productFor(id: string): ProductOption | undefined {
    return products.value.find((product) => String(product.id) === id);
}

function variantsFor(productId: string): VariantOption[] {
    return productFor(productId)?.variants ?? [];
}

/** Stock still promisable for a line's unit: on hand minus reservations. */
function availableFor(line: OrderLine): number | null {
    const product = productFor(line.product_id);

    if (!product) {
        return null;
    }

    const rows = product.inventory_items ?? [];
    const matching = line.product_variant_id
        ? rows.filter(
              (row) =>
                  String(row.product_variant_id) === line.product_variant_id,
          )
        : rows;

    if (!matching.length) {
        return 0;
    }

    return matching.reduce(
        (total, row) => total + (row.quantity_on_hand - row.quantity_reserved),
        0,
    );
}

/** Catalogue price for a line: the variant's, else the product's. */
function catalogPrice(line: OrderLine): number {
    const product = productFor(line.product_id);

    if (!product) {
        return 0;
    }

    if (line.product_variant_id) {
        const variant = product.variants.find(
            (candidate) => String(candidate.id) === line.product_variant_id,
        );

        if (variant?.selling_price !== null && variant?.selling_price !== undefined) {
            return Number(variant.selling_price);
        }
    }

    return Number(product.selling_price ?? 0);
}

function lineTotal(line: OrderLine): number {
    const price =
        line.unit_price === '' ? catalogPrice(line) : Number(line.unit_price);

    return price * Number(line.quantity || 0);
}

const subtotal = computed(() =>
    form.items.reduce((total, line) => total + lineTotal(line), 0),
);

const total = computed(
    () =>
        subtotal.value -
        Number(form.discount_total || 0) +
        Number(form.tax_total || 0),
);

/** Lines asking for more than is available; confirmation would reject these. */
const oversold = computed(() =>
    form.items.filter((line) => {
        const available = availableFor(line);

        return (
            line.product_id !== '' &&
            available !== null &&
            Number(line.quantity || 0) > available
        );
    }),
);

/**
 * Picking a customer fills the contact fields from their record, which is what
 * the backend snapshots onto the order anyway — showing it up front means no
 * surprise about what gets stored.
 */
watch(
    () => form.customer_id,
    (id) => {
        if (!id) {
            return;
        }

        const customer = (props.options.customers ?? []).find(
            (candidate) => String(candidate.id) === id,
        );

        if (customer) {
            form.customer_name = customer.name;
            form.customer_email = customer.email ?? '';
        }
    },
);

/** Changing the product invalidates the variant and the price beneath it. */
function onProductChange(line: OrderLine) {
    line.product_variant_id = '';
    line.unit_price = '';
}

function addLine() {
    form.items.push({
        product_id: '',
        product_variant_id: '',
        quantity: 1,
        unit_price: '',
    });
}

function removeLine(index: number) {
    form.items.splice(index, 1);
}

function submit() {
    const options = { preserveScroll: true };

    if ((props.method ?? 'post') === 'put') {
        form.put(props.action, options);

        return;
    }

    form.post(props.action, options);
}

// A brand new order opens with one empty line, so there is something to fill in.
if (!form.items.length) {
    addLine();
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                <FormSection
                    title="Items in order"
                    description="Leave a price blank to use the catalogue price at the moment the order is saved."
                >
                    <template #actions>
                        <Button
                            type="button"
                            variant="outline"
                            size="dense"
                            @click="addLine"
                        >
                            <PlusIcon />
                            Add item
                        </Button>
                    </template>

                    <p v-if="form.errors.items" class="text-2xs text-danger">
                        {{ form.errors.items }}
                    </p>

                    <div
                        v-for="(line, index) in form.items"
                        :key="index"
                        class="rounded-md border border-border p-3"
                    >
                        <div class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1.4fr)_80px_110px_auto]">
                            <FormField
                                label="Product"
                                :error="form.errors[`items.${index}.product_id`]"
                            >
                                <Select
                                    v-model="line.product_id"
                                    @update:model-value="onProductChange(line)"
                                >
                                    <option value="">Select product</option>
                                    <option
                                        v-for="product in products"
                                        :key="product.id"
                                        :value="product.id"
                                    >
                                        {{ product.name }}
                                    </option>
                                </Select>
                            </FormField>

                            <FormField
                                label="Variant"
                                :error="
                                    form.errors[`items.${index}.product_variant_id`]
                                "
                            >
                                <Select
                                    v-model="line.product_variant_id"
                                    :disabled="!variantsFor(line.product_id).length"
                                >
                                    <option value="">
                                        {{
                                            variantsFor(line.product_id).length
                                                ? 'Select variant'
                                                : 'No variants'
                                        }}
                                    </option>
                                    <option
                                        v-for="variant in variantsFor(line.product_id)"
                                        :key="variant.id"
                                        :value="variant.id"
                                    >
                                        {{ variant.name }}
                                    </option>
                                </Select>
                            </FormField>

                            <FormField
                                label="Qty"
                                :error="form.errors[`items.${index}.quantity`]"
                            >
                                <Input
                                    v-model.number="line.quantity"
                                    type="number"
                                    min="1"
                                />
                            </FormField>

                            <FormField
                                label="Unit price"
                                :error="form.errors[`items.${index}.unit_price`]"
                            >
                                <Input
                                    v-model="line.unit_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :placeholder="String(catalogPrice(line) || '')"
                                />
                            </FormField>

                            <div class="flex items-end">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-dense"
                                    aria-label="Remove item"
                                    :disabled="form.items.length === 1"
                                    @click="removeLine(index)"
                                >
                                    <Trash2Icon />
                                </Button>
                            </div>
                        </div>

                        <div
                            v-if="line.product_id"
                            class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-dashed border-border pt-2 text-2xs"
                        >
                            <span class="text-muted-foreground">
                                Available to promise:
                                <strong>{{ availableFor(line) ?? '—' }}</strong>
                            </span>
                            <span class="font-medium">
                                Line total {{ money(lineTotal(line), form.currency) }}
                            </span>
                        </div>
                    </div>
                </FormSection>

                <FormSection title="Notes">
                    <FormField :error="form.errors.notes">
                        <Textarea
                            v-model="form.notes"
                            :rows="3"
                            placeholder="Anything the store needs to know about this order"
                        />
                    </FormField>
                </FormSection>
            </div>

            <div class="space-y-5">
                <FormSection
                    title="Customer"
                    description="Pick a customer, or leave it blank and name a walk-in buyer."
                >
                    <FormField label="Customer" :error="form.errors.customer_id">
                        <Select v-model="form.customer_id">
                            <option value="">Walk-in — no record</option>
                            <option
                                v-for="customer in props.options.customers ?? []"
                                :key="customer.id"
                                :value="customer.id"
                            >
                                {{ customer.name }} ({{ customer.code }})
                            </option>
                        </Select>
                    </FormField>

                    <FormField
                        label="Name"
                        :error="form.errors.customer_name"
                        :required="!form.customer_id"
                        hint="Stored on the order itself, so it still reads correctly if the customer record changes later."
                    >
                        <Input
                            v-model="form.customer_name"
                            :invalid="Boolean(form.errors.customer_name)"
                        />
                    </FormField>

                    <FormField label="Email" :error="form.errors.customer_email">
                        <Input v-model="form.customer_email" type="email" />
                    </FormField>

                    <FormField label="Phone" :error="form.errors.customer_phone">
                        <Input v-model="form.customer_phone" />
                    </FormField>

                    <FormField
                        label="Delivery address"
                        :error="form.errors.delivery_address"
                    >
                        <Textarea v-model="form.delivery_address" :rows="3" />
                    </FormField>
                </FormSection>

                <FormSection title="Order data">
                    <FormField
                        v-if="!hideStatus"
                        label="Status"
                        :error="form.errors.status_id"
                        hint="A draft reserves nothing. Stock is reserved only when the order is confirmed."
                    >
                        <Select v-model.number="form.status_id">
                            <option
                                v-for="status in selectableStatuses"
                                :key="status.id"
                                :value="status.id"
                            >
                                {{ status.label }}
                            </option>
                        </Select>
                    </FormField>

                    <FormField label="Currency" :error="form.errors.currency">
                        <Input v-model="form.currency" maxlength="3" class="font-mono" />
                    </FormField>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField label="Discount" :error="form.errors.discount_total">
                            <Input
                                v-model="form.discount_total"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                        </FormField>

                        <FormField label="Tax" :error="form.errors.tax_total">
                            <Input
                                v-model="form.tax_total"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                        </FormField>
                    </div>
                </FormSection>

                <FormSection title="Summary">
                    <dl class="space-y-2 text-2sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Subtotal</dt>
                            <dd>{{ money(subtotal, form.currency) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Discount</dt>
                            <dd>
                                −{{
                                    money(Number(form.discount_total || 0), form.currency)
                                }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Tax</dt>
                            <dd>
                                {{ money(Number(form.tax_total || 0), form.currency) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between border-t border-dashed border-border pt-2 font-semibold"
                        >
                            <dt>Total</dt>
                            <dd>{{ money(total, form.currency) }}</dd>
                        </div>
                    </dl>

                    <p class="text-2xs text-muted-foreground">
                        The backend recalculates these from the saved lines.
                    </p>
                </FormSection>
            </div>
        </div>

        <div
            v-if="oversold.length"
            class="flex items-start gap-2.5 rounded-md border border-warning/20 bg-warning-soft px-4 py-3 text-2sm text-warning"
        >
            <TriangleAlertIcon class="mt-px size-4 shrink-0" />
            <p>
                {{ oversold.length }}
                {{ oversold.length === 1 ? 'line asks' : 'lines ask' }}
                for more than is available to promise. Saving is fine — a draft
                reserves nothing — but confirming will be rejected unless stock
                arrives first.
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <Badge v-if="form.isDirty" variant="outline">Unsaved changes</Badge>

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
                @click="$emit('cancel')"
            >
                Cancel
            </Button>
            <Button type="submit" size="dense" :disabled="form.processing">
                {{ submitLabel ?? 'Save order' }}
            </Button>
        </div>
    </form>
</template>
