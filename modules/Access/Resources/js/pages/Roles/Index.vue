<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import CreateResourceCard from '@/components/CreateResourceCard.vue';
import DataCard from '@/components/DataCard.vue';
import Pagination from '@/components/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import routes from '@/routes/access/roles';
import type { Paginated } from '@/types';

const props = defineProps<{
    roles: Paginated<Record<string, any>>;
    filters: Record<string, unknown>;
    permissionGroups: Record<string, string[]>;
}>();

const { can } = usePermissions();

const filters = reactive({
    search: (props.filters.search as string) ?? '',
});

watch(filters, (value) => {
    router.get(routes.index.url(), { ...value }, {
        preserveState: true,
        replace: true,
    });
});

const roleFields = [{ name: 'name', label: 'Role name', required: true }];

/** Reads a dotted path off a row so the table stays declarative. */
function value(row: Record<string, any>, path: string): unknown {
    return path.split('.').reduce<any>((carry, key) => carry?.[key], row) ?? '—';
}
</script>

<template>
    <Head title="Roles" />

    <AppLayout title="Roles">
        <div class="space-y-6">
            <CreateResourceCard
                v-if="can('roles.create')"
                title="New role"
                description="Permissions are granted from the role's detail page."
                action="/access/roles"
                :fields="roleFields"
            />

            <DataCard title="Filters">
                <div class="flex flex-wrap gap-3 p-4">
                    <input
                        v-model="filters.search"
                        placeholder="Search role"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                </div>
            </DataCard>

            <DataCard title="Roles">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-4 py-2">Role</th>
                                <th class="px-4 py-2">Users</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in props.roles.data"
                                :key="row.id"
                                class="border-t border-border"
                            >
                                <td class="px-4 py-2">
                                    <Link
                                        :href="routes.show.url(row.id)"
                                        class="underline"
                                        >{{ value(row, 'name') }}</Link
                                    >
                                </td>
                                <td class="px-4 py-2">{{ value(row, 'users_count') }}</td>
                            </tr>
                            <tr v-if="!props.roles.data.length">
                                <td
                                    class="px-4 py-3 text-muted-foreground"
                                    colspan="2"
                                >
                                    Nothing to show yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    :links="props.roles.links"
                    :from="props.roles.from"
                    :to="props.roles.to"
                    :total="props.roles.total"
                />
            </DataCard>
        </div>
    </AppLayout>
</template>
