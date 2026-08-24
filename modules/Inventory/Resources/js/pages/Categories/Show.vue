<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import DataCard from '@/components/DataCard.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import categoriesRoutes from '@/routes/inventory/categories';

interface Category {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    status: string;
    products_count: number;
    parent: { id: string; name: string } | null;
    children: Array<{ id: string; name: string }>;
}

const props = defineProps<{ category: Category }>();

const { can } = usePermissions();

const form = useForm({
    name: props.category.name,
    description: props.category.description ?? '',
    status: props.category.status,
});
</script>

<template>
    <Head :title="category.name" />

    <AppLayout :title="category.name">
        <div class="grid gap-6 lg:grid-cols-2">
            <DataCard title="Details">
                <dl class="space-y-2 p-4 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Slug</dt>
                        <dd>{{ category.slug }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Parent</dt>
                        <dd>{{ category.parent?.name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Products</dt>
                        <dd>{{ category.products_count }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Children</dt>
                        <dd>{{ category.children.length }}</dd>
                    </div>
                </dl>
            </DataCard>

            <DataCard v-if="can('categories.update')" title="Edit">
                <form
                    class="space-y-3 p-4"
                    @submit.prevent="
                        form.put(categoriesRoutes.update.url(category.id))
                    "
                >
                    <input
                        v-model="form.name"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <select
                        v-model="form.status"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    >
                        <option value="active">active</option>
                        <option value="inactive">inactive</option>
                    </select>
                    <Button type="submit" :disabled="form.processing">Save</Button>
                </form>
            </DataCard>
        </div>
    </AppLayout>
</template>
