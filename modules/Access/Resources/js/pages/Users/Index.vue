<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { PlusIcon, PowerIcon, Trash2Icon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import { FormField, FormSection } from '@/components/form';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableToolbar from '@/components/TableToolbar.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Drawer } from '@/components/ui/drawer';
import { Dropdown, DropdownItem } from '@/components/ui/dropdown';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { usePageErrors } from '@/composables/usePageErrors';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { date, number } from '@/lib/format';
import userRoutes from '@/routes/access/users';
import type { Paginated } from '@/types';

interface UserRow {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    created_at: string;
    roles: Array<{ id: number; name: string }>;
}

const props = defineProps<{
    users: Paginated<UserRow>;
    filters: Record<string, unknown>;
    roles: string[];
}>();

const { can } = usePermissions();
const { firstOf } = usePageErrors();

const { params, loading, toggleSort, sortState, reset } = useTableQuery({
    url: userRoutes.index.url(),
    filters: props.filters,
    only: ['users', 'filters'],
});

const rows = computed(() => props.users.data);
const creating = ref(false);
const confirming = ref<UserRow | null>(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as string[],
});

const columns: Column[] = [
    { key: 'name', label: 'User', sort: 'name', width: '260px' },
    { key: 'roles', label: 'Roles', width: '240px' },
    { key: 'is_active', label: 'Status', sort: 'is_active', width: '110px' },
    { key: 'created_at', label: 'Joined', sort: 'created_at', width: '140px' },
];

const breadcrumbs = [
    { label: 'Administration' },
    { label: 'Access' },
    { label: 'Users' },
];

function create() {
    form.post(userRoutes.store.url(), {
        onSuccess: () => {
            form.reset();
            creating.value = false;
        },
    });
}

function toggleActive(row: UserRow) {
    router.patch(`/access/users/${row.id}/status`, {}, { preserveScroll: true });
}

function destroy() {
    if (!confirming.value) {
        return;
    }

    router.delete(userRoutes.destroy.url(Number(confirming.value.id)), {
        preserveScroll: true,
        onFinish: () => (confirming.value = null),
    });
}
</script>

<template>
    <Head title="Users" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="Users"
            :description="`${number(props.users.total)} accounts. A deactivated account is signed out on its next request.`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Button
                    v-if="can('users.create')"
                    size="dense"
                    @click="creating = true"
                >
                    <PlusIcon />
                    New user
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <template #title><CardTitle>Accounts</CardTitle></template>
            </CardHeader>

            <TableToolbar
                v-model:search="params.search"
                v-model:per-page="params.per_page"
                search-placeholder="Search name or email"
                @clear="reset"
            >
                <template #filters>
                    <Select v-model="params.role" class="w-44">
                        <option value="">All roles</option>
                        <option v-for="role in props.roles" :key="role" :value="role">
                            {{ role }}
                        </option>
                    </Select>

                    <!-- The backend filters on is_active, so the values are
                         the booleans it validates. -->
                    <Select v-model="params.is_active" class="w-36">
                        <option value="">Any status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </Select>
                </template>
            </TableToolbar>

            <DataTable
                :columns="columns"
                :rows="rows"
                :loading="loading"
                :sort-state="sortState"
                empty-title="No users"
                @sort="toggleSort"
            >
                <template #cell-name="{ row }">
                    <span class="flex items-center gap-2.5">
                        <Avatar :name="row.name" class="size-7" />
                        <span class="min-w-0">
                            <Link
                                :href="userRoutes.show.url(row.id)"
                                class="block truncate font-medium hover:underline"
                                >{{ row.name }}</Link
                            >
                            <span
                                class="block truncate text-[11px] text-muted-foreground"
                                >{{ row.email }}</span
                            >
                        </span>
                    </span>
                </template>

                <template #cell-roles="{ row }">
                    <span class="flex flex-wrap gap-1">
                        <Badge
                            v-for="role in row.roles"
                            :key="role.id"
                            variant="outline"
                            size="sm"
                            >{{ role.name }}</Badge
                        >
                        <span
                            v-if="!row.roles.length"
                            class="text-muted-foreground"
                            >No role</span
                        >
                    </span>
                </template>

                <template #cell-is_active="{ row }">
                    <Badge :variant="row.is_active ? 'success' : 'neutral'" size="sm">
                        {{ row.is_active ? 'Active' : 'Inactive' }}
                    </Badge>
                </template>

                <template #cell-created_at="{ row }">
                    <span class="text-muted-foreground">{{
                        date(row.created_at)
                    }}</span>
                </template>

                <template #actions="{ row }">
                    <Dropdown>
                        <template #trigger>
                            <Button
                                variant="ghost"
                                size="icon-dense"
                                aria-label="Row actions"
                            >
                                <span class="text-base leading-none">⋯</span>
                            </Button>
                        </template>

                        <DropdownItem as-child>
                            <Link
                                :href="userRoutes.show.url(row.id)"
                                class="flex w-full items-center gap-2"
                                >View</Link
                            >
                        </DropdownItem>

                        <DropdownItem
                            v-if="can('users.update')"
                            @select="toggleActive(row)"
                        >
                            <PowerIcon />
                            {{ row.is_active ? 'Deactivate' : 'Activate' }}
                        </DropdownItem>

                        <DropdownItem
                            v-if="can('users.delete')"
                            destructive
                            @select="confirming = row"
                        >
                            <Trash2Icon />
                            Delete
                        </DropdownItem>
                    </Dropdown>
                </template>
            </DataTable>

            <Pagination
                :links="props.users.links"
                :from="props.users.from"
                :to="props.users.to"
                :total="props.users.total"
            />
        </Card>

        <Drawer
            :open="creating"
            title="New user"
            description="Roles bundle permissions; a role can never be granted more than the granting user holds."
            @update:open="creating = $event"
        >
            <FormSection title="Account">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Name" :error="form.errors.name" required>
                        <Input v-model="form.name" />
                    </FormField>

                    <FormField label="Email" :error="form.errors.email" required>
                        <Input v-model="form.email" type="email" />
                    </FormField>

                    <FormField
                        label="Password"
                        :error="form.errors.password"
                        required
                    >
                        <Input v-model="form.password" type="password" />
                    </FormField>

                    <FormField label="Confirm password">
                        <Input
                            v-model="form.password_confirmation"
                            type="password"
                        />
                    </FormField>
                </div>

                <FormField label="Roles" :error="form.errors.roles">
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="role in props.roles"
                            :key="role"
                            class="flex h-8.5 items-center gap-2 rounded-md border border-input px-3 text-xs shadow-xs"
                        >
                            <input
                                v-model="form.roles"
                                type="checkbox"
                                :value="role"
                            />
                            {{ role }}
                        </label>
                    </div>
                </FormField>
            </FormSection>

            <template #footer>
                <Button variant="outline" size="dense" @click="creating = false">
                    Cancel
                </Button>
                <Button size="dense" :disabled="form.processing" @click="create">
                    Create user
                </Button>
            </template>
        </Drawer>

        <Drawer
            :open="Boolean(confirming)"
            title="Delete user"
            size="sm"
            @update:open="confirming = null"
        >
            <p class="text-[0.8125rem] text-muted-foreground">
                Deleting an account is not the same as revoking access —
                deactivating keeps their history attributable.
            </p>

            <p v-if="firstOf('user', 'role')" class="mt-3 text-[11px] text-danger">
                {{ firstOf('user', 'role') }}
            </p>

            <template #footer>
                <Button variant="outline" size="dense" @click="confirming = null">
                    Cancel
                </Button>
                <Button variant="destructive" size="dense" @click="destroy">
                    Delete user
                </Button>
            </template>
        </Drawer>
    </AppLayout>
</template>
