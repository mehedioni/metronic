<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FormField, FormSection, Textarea } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';

interface Parent {
    id: number;
    name: string;
}

export interface CategoryPayload {
    name: string;
    slug: string;
    description: string;
    parent_id: string;
    status: string;
}

const props = defineProps<{
    category?: Partial<CategoryPayload> & { id?: string };
    parents: Parent[];
    statuses: string[];
    action: string;
    method?: 'post' | 'put';
    submitLabel?: string;
    /** Omit inside a drawer and listen for @cancel instead. */
    cancelHref?: string;
}>();

const emit = defineEmits<{
    cancel: [];
}>();

const form = useForm<CategoryPayload>({
    name: props.category?.name ?? '',
    slug: props.category?.slug ?? '',
    description: props.category?.description ?? '',
    parent_id: props.category?.parent_id ?? '',
    status: props.category?.status ?? 'active',
});

function submit() {
    const options = { preserveScroll: true };

    if ((props.method ?? 'post') === 'put') {
        form.put(props.action, options);

        return;
    }

    form.post(props.action, options);
}
</script>

<template>
    <form class="max-w-2xl space-y-5" @submit.prevent="submit">
        <FormSection title="Category">
            <FormField label="Name" :error="form.errors.name" required>
                <Input
                    v-model="form.name"
                    placeholder="Category name"
                    :invalid="Boolean(form.errors.name)"
                />
            </FormField>

            <FormField
                label="Slug"
                :error="form.errors.slug"
                hint="Left blank, one is generated from the name."
            >
                <Input
                    v-model="form.slug"
                    placeholder="category-name"
                    class="font-mono"
                    :invalid="Boolean(form.errors.slug)"
                />
            </FormField>

            <FormField
                label="Parent category"
                :error="form.errors.parent_id"
                hint="A category cannot be its own ancestor."
            >
                <Select v-model="form.parent_id">
                    <option value="">No parent — top level</option>
                    <option
                        v-for="parent in parents"
                        :key="parent.id"
                        :value="parent.id"
                    >
                        {{ parent.name }}
                    </option>
                </Select>
            </FormField>

            <FormField label="Status" :error="form.errors.status">
                <Select v-model="form.status">
                    <option
                        v-for="status in statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ status }}
                    </option>
                </Select>
            </FormField>

            <FormField label="Description" :error="form.errors.description">
                <Textarea v-model="form.description" :rows="4" />
            </FormField>
        </FormSection>

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
