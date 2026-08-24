<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import DataCard from '@/components/DataCard.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import categoriesRoutes from '@/routes/inventory/categories';
import type { Paginated } from '@/types';

interface Category {
    id: string;
    name: string;
    slug: string;
    status: string;
    products_count: number;
    parent: { id: string; name: string } | null;
}

const props = defineProps<{
    categories: Paginated<Category>;
    filters: Record<string, unknown>;
    statuses: string[];
    parents: Array<{ id: string; name: string }>;
}>();

const { can } = usePermissions();

const filters = reactive({
    search: (props.filters.search as string) ?? '',
    status: (props.filters.status as string) ?? '',
});

watch(filters, (value) => {
    router.get(categoriesRoutes.index.url(), { ...value }, {
        preserveState: true,
        replace: true,
    });
});

const form = useForm({
    name: '',
    slug: '',
    parent_id: '',
    description: '',
    status: 'active',
});

function create() {
    form.post(categoriesRoutes.store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function destroy(category: Category) {
    router.delete(categoriesRoutes.destroy.url(category.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Categories" />

    <AppLayout title="Categories">
        <div class="space-y-6">
            <DataCard title="Filters">
                <div class="flex flex-wrap gap-3 p-4">
                    <input
                        v-model="filters.search"
                        placeholder="Search name or slug"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <select
                        v-model="filters.status"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option v-for="status in statuses" :key="status" :value="status">
                            {{ status }}
                        </option>
                    </select>
                </div>
            </DataCard>

            <DataCard v-if="can('categories.create')" title="New category">
                <form class="grid gap-3 p-4 sm:grid-cols-2" @submit.prevent="create">
                    <div>
                        <input
                            v-model="form.name"
                            placeholder="Name"
                            required
                            class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                        />
                        <p v-if="form.errors.name" class="text-sm text-red-500">
                            {{ form.errors.name }}
                        </p>
                    </div>
                    <select
                        v-model="form.parent_id"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    >
                        <option value="">No parent</option>
                        <option v-for="parent in parents" :key="parent.id" :value="parent.id">
                            {{ parent.name }}
                        </option>
                    </select>
                    <input
                        v-model="form.description"
                        placeholder="Description"
                        class="rounded border border-border bg-background px-3 py-2 text-sm sm:col-span-2"
                    />
                    <div>
                        <Button type="submit" :disabled="form.processing">Create</Button>
                    </div>
                </form>
            </DataCard>

            <DataCard title="All categories">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-2">Name</th>
                            <th class="px-4 py-2">Parent</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2 text-right">Products</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="category in categories.data"
                            :key="category.id"
                            class="border-t border-border"
                        >
                            <td class="px-4 py-2">
                                <Link
                                    :href="categoriesRoutes.show.url(category.id)"
                                    class="underline"
                                    >{{ category.name }}</Link
                                >
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{ category.parent?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-2 capitalize">{{ category.status }}</td>
                            <td class="px-4 py-2 text-right">
                                {{ category.products_count }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                <Button
                                    v-if="can('categories.delete')"
                                    variant="ghost"
                                    @click="destroy(category)"
                                    >Delete</Button
                                >
                            </td>
                        </tr>
                        <tr v-if="!categories.data.length">
                            <td class="px-4 py-3 text-muted-foreground" colspan="5">
                                No categories yet.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <Pagination
                    :links="categories.links"
                    :from="categories.from"
                    :to="categories.to"
                    :total="categories.total"
                />
            </DataCard>
        </div>
    </AppLayout>
</template>
