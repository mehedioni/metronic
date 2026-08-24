<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import CreateResourceCard from '@/components/CreateResourceCard.vue';
import DataCard from '@/components/DataCard.vue';
import Pagination from '@/components/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import routes from '@/routes/access/users';
import type { Paginated } from '@/types';

const props = defineProps<{
    users: Paginated<Record<string, any>>;
    filters: Record<string, unknown>;
    roles: string[];
}>();

const { can } = usePermissions();

const filters = reactive({
    search: (props.filters.search as string) ?? '',
    role: (props.filters.role as string) ?? '',
});

watch(filters, (value) => {
    router.get(routes.index.url(), { ...value }, {
        preserveState: true,
        replace: true,
    });
});

const userFields = computed(() => [
    { name: 'name', label: 'Name', required: true },
    { name: 'email', label: 'Email', type: 'email' as const, required: true },
    { name: 'password', label: 'Password', type: 'password' as const, required: true },
    {
        name: 'password_confirmation',
        label: 'Confirm password',
        type: 'password' as const,
        required: true,
    },
]);

/** Reads a dotted path off a row so the table stays declarative. */
function value(row: Record<string, any>, path: string): unknown {
    return path.split('.').reduce<any>((carry, key) => carry?.[key], row) ?? '—';
}
</script>

<template>
    <Head title="Users" />

    <AppLayout title="Users">
        <div class="space-y-6">
            <CreateResourceCard
                v-if="can('users.create')"
                title="New user"
                description="Roles are assigned from the user's detail page."
                action="/access/users"
                :fields="userFields"
            />

            <DataCard title="Filters">
                <div class="flex flex-wrap gap-3 p-4">
                    <input
                        v-model="filters.search"
                        placeholder="Search name or email"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <select
                        v-model="filters.role"
                        class="rounded border border-border bg-background px-3 py-2 text-sm"
                    >
                        <option value="">All roles</option>
                        <option v-for="option in props.roles" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                </div>
            </DataCard>

            <DataCard title="Users">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-4 py-2">Name</th>
                                <th class="px-4 py-2">Email</th>
                                <th class="px-4 py-2">Active</th>
                                <th class="px-4 py-2">Last login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in props.users.data"
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
                                <td class="px-4 py-2">{{ value(row, 'email') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'is_active') }}</td>
                                <td class="px-4 py-2">{{ value(row, 'last_login_at') }}</td>
                            </tr>
                            <tr v-if="!props.users.data.length">
                                <td
                                    class="px-4 py-3 text-muted-foreground"
                                    colspan="4"
                                >
                                    Nothing to show yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    :links="props.users.links"
                    :from="props.users.from"
                    :to="props.users.to"
                    :total="props.users.total"
                />
            </DataCard>
        </div>
    </AppLayout>
</template>
