<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import DataCard from '@/components/DataCard.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import rolesRoutes from '@/routes/access/roles';

interface Role {
    id: number;
    name: string;
    permissions: Array<{ id: number; name: string }>;
}

const props = defineProps<{
    role: Role;
    permissionGroups: Record<string, string[]>;
}>();

const { can } = usePermissions();

const form = useForm({
    permissions: props.role.permissions.map((permission) => permission.name),
});

const isSuperAdmin = props.role.name === 'Super Admin';
</script>

<template>
    <Head :title="role.name" />

    <AppLayout :title="role.name">
        <DataCard
            title="Permissions"
            :description="
                isSuperAdmin
                    ? 'Super Admin bypasses every permission check and is not editable.'
                    : 'You can only grant permissions you hold yourself.'
            "
        >
            <form
                class="space-y-4 p-4"
                @submit.prevent="form.put(rolesRoutes.update.url(role.id))"
            >
                <div
                    v-for="(permissions, group) in permissionGroups"
                    :key="group"
                    class="space-y-1"
                >
                    <h3 class="text-sm font-semibold capitalize">{{ group }}</h3>
                    <div class="grid gap-1 sm:grid-cols-2 lg:grid-cols-4">
                        <label
                            v-for="permission in permissions"
                            :key="permission"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                v-model="form.permissions"
                                type="checkbox"
                                :value="permission"
                                :disabled="isSuperAdmin || !can('permissions.manage')"
                            />
                            {{ permission }}
                        </label>
                    </div>
                </div>

                <Button
                    v-if="!isSuperAdmin && can('roles.update')"
                    type="submit"
                    :disabled="form.processing"
                    >Save permissions</Button
                >
                <p
                    v-for="(error, field) in form.errors"
                    :key="field"
                    class="text-sm text-red-500"
                >
                    {{ error }}
                </p>
            </form>
        </DataCard>
    </AppLayout>
</template>
