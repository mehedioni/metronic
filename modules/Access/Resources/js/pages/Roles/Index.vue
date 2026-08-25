<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { PlusIcon, Trash2Icon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import { FormField, FormSection } from '@/components/form';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableToolbar from '@/components/TableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Drawer } from '@/components/ui/drawer';
import { Dropdown, DropdownItem } from '@/components/ui/dropdown';
import { Input } from '@/components/ui/input';
import { usePageErrors } from '@/composables/usePageErrors';
import { usePermissions } from '@/composables/usePermissions';
import { useTableQuery } from '@/composables/useTableQuery';
import AppLayout from '@/layouts/AppLayout.vue';
import { number } from '@/lib/format';
import { humanize } from '@/lib/status';
import roleRoutes from '@/routes/access/roles';
import type { Paginated } from '@/types';

interface RoleRow {
    id: number;
    name: string;
    users_count: number;
    permissions: Array<{ id: number; name: string }>;
}

const props = defineProps<{
    roles: Paginated<RoleRow>;
    filters: Record<string, unknown>;
    permissionGroups: Record<string, string[]>;
}>();

const { can } = usePermissions();
const { firstOf } = usePageErrors();

const { params, loading, reset } = useTableQuery({
    url: roleRoutes.index.url(),
    filters: props.filters,
    only: ['roles', 'filters'],
});

const rows = computed(() => props.roles.data);
const creating = ref(false);
const confirming = ref<RoleRow | null>(null);

const form = useForm({
    name: '',
    permissions: [] as string[],
});

const columns: Column[] = [
    { key: 'name', label: 'Role', width: '220px' },
    { key: 'users_count', label: 'Users', align: 'center', width: '90px' },
    { key: 'permissions', label: 'Permissions', width: '120px', align: 'center' },
];

const breadcrumbs = [
    { label: 'Administration' },
    { label: 'Access' },
    { label: 'Roles' },
];

/** Tick or clear a whole permission group at once. */
function toggleGroup(group: string, permissions: string[]) {
    const allOn = permissions.every((name) => form.permissions.includes(name));

    form.permissions = allOn
        ? form.permissions.filter((name) => !permissions.includes(name))
        : [...new Set([...form.permissions, ...permissions])];
}

function create() {
    form.post(roleRoutes.store.url(), {
        onSuccess: () => {
            form.reset();
            creating.value = false;
        },
    });
}

function destroy() {
    if (!confirming.value) {
        return;
    }

    router.delete(roleRoutes.destroy.url(confirming.value.id), {
        preserveScroll: true,
        onFinish: () => (confirming.value = null),
    });
}
</script>

<template>
    <Head title="Roles" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="Roles"
            description="Roles only bundle permissions. Authorization is always checked against a permission, never a role name."
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Button
                    v-if="can('roles.create')"
                    size="dense"
                    @click="creating = true"
                >
                    <PlusIcon />
                    New role
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle
                        :description="`${number(props.roles.total)} roles`"
                        >Role list</CardTitle
                    >
                </template>
            </CardHeader>

            <TableToolbar
                v-model:search="params.search"
                v-model:per-page="params.per_page"
                search-placeholder="Search role"
                @clear="reset"
            />

            <DataTable
                :columns="columns"
                :rows="rows"
                :loading="loading"
                empty-title="No roles"
            >
                <template #cell-name="{ row }">
                    <Link
                        :href="roleRoutes.show.url(row.id)"
                        class="font-medium hover:underline"
                        >{{ row.name }}</Link
                    >
                </template>

                <template #cell-permissions="{ row }">
                    <Badge variant="outline" size="sm">
                        {{ row.permissions.length }}
                    </Badge>
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
                                :href="roleRoutes.show.url(row.id)"
                                class="flex w-full items-center gap-2"
                                >View</Link
                            >
                        </DropdownItem>

                        <DropdownItem
                            v-if="can('roles.delete')"
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
                :links="props.roles.links"
                :from="props.roles.from"
                :to="props.roles.to"
                :total="props.roles.total"
            />
        </Card>

        <Drawer
            :open="creating"
            title="New role"
            description="You can only grant permissions you hold yourself."
            size="lg"
            @update:open="creating = $event"
        >
            <FormSection title="Role">
                <FormField label="Name" :error="form.errors.name" required>
                    <Input v-model="form.name" placeholder="Warehouse Lead" />
                </FormField>
            </FormSection>

            <FormSection title="Permissions" class="mt-5">
                <div
                    v-for="(permissions, group) in props.permissionGroups"
                    :key="group"
                    class="border-b border-dashed border-border pb-3 last:border-0 last:pb-0"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold">{{ humanize(group) }}</p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="dense"
                            @click="toggleGroup(group, permissions)"
                        >
                            Toggle all
                        </Button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="permission in permissions"
                            :key="permission"
                            class="flex h-8.5 items-center gap-2 rounded-md border border-input px-3 text-2xs shadow-xs"
                        >
                            <input
                                v-model="form.permissions"
                                type="checkbox"
                                :value="permission"
                            />
                            {{ permission }}
                        </label>
                    </div>
                </div>

                <p
                    v-if="form.errors.permissions || firstOf('role', 'permission')"
                    class="text-2xs text-danger"
                >
                    {{ form.errors.permissions ?? firstOf('role', 'permission') }}
                </p>
            </FormSection>

            <template #footer>
                <Button variant="outline" size="dense" @click="creating = false">
                    Cancel
                </Button>
                <Button size="dense" :disabled="form.processing" @click="create">
                    Create role
                </Button>
            </template>
        </Drawer>

        <Drawer
            :open="Boolean(confirming)"
            title="Delete role"
            size="sm"
            @update:open="confirming = null"
        >
            <p class="text-2sm text-muted-foreground">
                Users holding this role lose the permissions it bundles.
            </p>

            <p v-if="firstOf('role')" class="mt-3 text-2xs text-danger">
                {{ firstOf('role') }}
            </p>

            <template #footer>
                <Button variant="outline" size="dense" @click="confirming = null">
                    Cancel
                </Button>
                <Button variant="destructive" size="dense" @click="destroy">
                    Delete role
                </Button>
            </template>
        </Drawer>
    </AppLayout>
</template>
