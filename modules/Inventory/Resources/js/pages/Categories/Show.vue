<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PencilIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { number } from '@/lib/format';
import categories from '@/routes/inventory/categories';
import products from '@/routes/inventory/products';

interface Category {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    status: string;
    products_count: number;
    parent: { id: number; name: string } | null;
    children: Array<{ id: number; name: string }>;
}

const props = defineProps<{ category: Category }>();

const { can } = usePermissions();

const breadcrumbs = computed(() => [
    { label: 'Store Inventory' },
    { label: 'Categories', href: categories.index.url() },
    { label: props.category.name },
]);
</script>

<template>
    <Head :title="category.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            :title="category.name"
            :description="category.slug"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <StatusBadge :status="category.status" />

                <Button
                    v-if="can('categories.update')"
                    variant="outline"
                    size="dense"
                    as="a"
                    :href="categories.edit.url(category.id)"
                >
                    <PencilIcon />
                    Edit
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <template #title><CardTitle>Details</CardTitle></template>
                </CardHeader>

                <CardContent>
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted-foreground">Parent</dt>
                            <dd class="text-[0.8125rem]">
                                <Link
                                    v-if="category.parent"
                                    :href="categories.show.url(category.parent.id)"
                                    class="hover:underline"
                                    >{{ category.parent.name }}</Link
                                >
                                <span v-else>Top level</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Products</dt>
                            <dd class="text-[0.8125rem]">
                                <Link
                                    :href="
                                        products.index.url({
                                            query: { category_id: category.id },
                                        })
                                    "
                                    class="hover:underline"
                                >
                                    {{ number(category.products_count) }}
                                </Link>
                            </dd>
                        </div>
                    </dl>

                    <div v-if="category.description" class="mt-5">
                        <p class="mb-1 text-xs text-muted-foreground">
                            Description
                        </p>
                        <p class="whitespace-pre-line text-[0.8125rem] leading-relaxed">
                            {{ category.description }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="self-start">
                <CardHeader>
                    <template #title>
                        <CardTitle
                            :description="`${category.children.length} sub-categories`"
                            >Children</CardTitle
                        >
                    </template>
                </CardHeader>

                <ul class="divide-y divide-border">
                    <li
                        v-for="child in category.children"
                        :key="child.id"
                        class="px-5 py-3 text-[0.8125rem]"
                    >
                        <Link
                            :href="categories.show.url(child.id)"
                            class="hover:underline"
                            >{{ child.name }}</Link
                        >
                    </li>
                    <li
                        v-if="!category.children.length"
                        class="px-5 py-6 text-center text-xs text-muted-foreground"
                    >
                        No sub-categories.
                    </li>
                </ul>
            </Card>
        </div>
    </AppLayout>
</template>
