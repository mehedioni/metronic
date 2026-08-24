<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { FormSection } from '@/components/form';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePageErrors } from '@/composables/usePageErrors';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { humanize } from '@/lib/status';
import roleRoutes from '@/routes/access/roles';

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
const { firstOf } = usePageErrors();

const form = useForm({
    name: props.role.name,
    permissions: props.role.permissions.map((permission) => permission.name),
});

const breadcrumbs = computed(() => [
    { label: 'Administration' },
    { label: 'Roles', href: roleRoutes.index.url() },
    { label: props.role.name },
]);

const total = computed(
    () => Object.values(props.permissionGroups).flat().length,
);

function toggleGroup(group: string, permissions: string[]) {
    const allOn = permissions.every((name) => form.permissions.includes(name));

    form.permissions = allOn
        ? form.permissions.filter((name) => !permissions.includes(name))
        : [...new Set([...form.permissions, ...permissions])];
}
</script>

<template>
    <Head :title="role.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            :title="role.name"
            :description="`${form.permissions.length} of ${total} permissions granted`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Badge variant="outline">{{ role.permissions.length }} granted</Badge>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <template #title>
                    <CardTitle
                        description="You can only grant permissions you hold yourself; the backend refuses an escalation."
                        >Permissions</CardTitle
                    >
                </template>
            </CardHeader>

            <CardContent>
                <form
                    class="space-y-5"
                    @submit.prevent="
                        form.put(roleRoutes.update.url(role.id), {
                            preserveScroll: true,
                        })
                    "
                >
                    <FormSection
                        v-for="(permissions, group) in props.permissionGroups"
                        :key="group"
                        :title="humanize(group)"
                    >
                        <template #actions>
                            <Button
                                v-if="can('roles.update')"
                                type="button"
                                variant="ghost"
                                size="dense"
                                @click="toggleGroup(group, permissions)"
                            >
                                Toggle all
                            </Button>
                        </template>

                        <div class="flex flex-wrap gap-2">
                            <label
                                v-for="permission in permissions"
                                :key="permission"
                                class="flex h-8.5 items-center gap-2 rounded-md border border-input px-3 text-[11px] shadow-xs"
                                :class="
                                    can('roles.update')
                                        ? ''
                                        : 'pointer-events-none opacity-70'
                                "
                            >
                                <input
                                    v-model="form.permissions"
                                    type="checkbox"
                                    :value="permission"
                                    :disabled="!can('roles.update')"
                                />
                                {{ permission }}
                            </label>
                        </div>
                    </FormSection>

                    <p
                        v-if="form.errors.permissions || firstOf('role', 'permission')"
                        class="text-[11px] text-danger"
                    >
                        {{ form.errors.permissions ?? firstOf('role', 'permission') }}
                    </p>

                    <div v-if="can('roles.update')" class="flex justify-end">
                        <Button
                            type="submit"
                            size="dense"
                            :disabled="form.processing"
                            >Save permissions</Button
                        >
                    </div>
                </form>
            </CardContent>
        </Card>
    </AppLayout>
</template>
