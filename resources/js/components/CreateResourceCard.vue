<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import DataCard from '@/components/DataCard.vue';
import { Button } from '@/components/ui/button';

export interface CreateField {
    name: string;
    label: string;
    type?: 'text' | 'number' | 'email' | 'password' | 'select' | 'textarea';
    options?: Array<{ value: string | number; label: string }>;
    required?: boolean;
    value?: string | number;
}

const props = withDefaults(
    defineProps<{
        title: string;
        action: string;
        fields: CreateField[];
        submitLabel?: string;
        description?: string;
        /**
         * Field names that belong to a single nested line item. The backend
         * expects "items[0]" for documents such as orders and receipts; this
         * keeps the placeholder UI to one line without a bespoke page.
         */
        itemsFrom?: string[];
    }>(),
    { submitLabel: 'Create', itemsFrom: undefined, description: undefined },
);

type FieldValues = Record<string, string | number>;

const form = useForm<FieldValues>(
    Object.fromEntries(
        props.fields.map((field) => [field.name, field.value ?? '']),
    ) as FieldValues,
);

function submit() {
    const itemKeys = props.itemsFrom;

    if (!itemKeys?.length) {
        form.post(props.action, { preserveScroll: true });

        return;
    }

    form.transform((data) => {
        const item: Record<string, string | number> = {};
        const rest: Record<string, string | number> = {};

        Object.entries(data).forEach(([key, value]) => {
            if (itemKeys.includes(key)) {
                if (value !== '' && value !== null) {
                    item[key] = value;
                }

                return;
            }

            rest[key] = value;
        });

        return { ...rest, items: [item] };
    }).post(props.action, { preserveScroll: true });
}
</script>

<template>
    <DataCard :title="title" :description="description">
        <form class="grid gap-3 p-4 sm:grid-cols-2" @submit.prevent="submit">
            <div v-for="field in fields" :key="field.name" class="space-y-1">
                <label class="text-sm text-muted-foreground" :for="field.name">
                    {{ field.label }}
                </label>

                <select
                    v-if="field.type === 'select'"
                    :id="field.name"
                    v-model="form[field.name]"
                    class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                >
                    <option value="">—</option>
                    <option
                        v-for="option in field.options"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>

                <textarea
                    v-else-if="field.type === 'textarea'"
                    :id="field.name"
                    v-model="form[field.name]"
                    rows="2"
                    class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                />

                <input
                    v-else
                    :id="field.name"
                    v-model="form[field.name]"
                    :type="field.type ?? 'text'"
                    :required="field.required"
                    class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                />

                <p v-if="form.errors[field.name]" class="text-sm text-red-500">
                    {{ form.errors[field.name] }}
                </p>
            </div>

            <div class="sm:col-span-2">
                <Button type="submit" :disabled="form.processing">{{
                    submitLabel
                }}</Button>
                <p
                    v-for="(error, key) in form.errors"
                    :key="key"
                    class="text-sm text-red-500"
                >
                    {{ error }}
                </p>
            </div>
        </form>
    </DataCard>
</template>
