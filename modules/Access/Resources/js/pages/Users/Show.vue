<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { PowerIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { FormField, FormSection } from '@/components/form';
import PageHeader from '@/components/PageHeader.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { usePageErrors } from '@/composables/usePageErrors';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateTime } from '@/lib/format';
import userRoutes from '@/routes/access/users';

interface AccessUser {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    created_at: string;
    email_verified_at: string | null;
    roles: Array<{ id: number; name: string }>;
}

const props = defineProps<{ user: AccessUser; roles: string[] }>();

const { can } = usePermissions();
const { firstOf } = usePageErrors();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    roles: props.user.roles.map((role) => role.name),
});

const breadcrumbs = computed(() => [
    { label: 'Administration' },
    { label: 'Users', href: userRoutes.index.url() },
    { label: props.user.name },
]);

function toggleActive() {
    router.patch(`/access/users/${props.user.id}/status`, {}, {
        preserveScroll: true,
    });
}

function save() {
    form.put(userRoutes.update.url(props.user.id), {
        preserveScroll: true,
        // Never leave a typed password sitting in the form after a save.
        onSuccess: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="user.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            :title="user.name"
            :description="user.email"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Badge :variant="user.is_active ? 'success' : 'neutral'">
                    {{ user.is_active ? 'Active' : 'Inactive' }}
                </Badge>

                <Button
                    v-if="can('users.update')"
                    variant="outline"
                    size="dense"
                    @click="toggleActive"
                >
                    <PowerIcon />
                    {{ user.is_active ? 'Deactivate' : 'Activate' }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <template #title>
                        <CardTitle
                            description="A role can never be granted more than the granting user holds."
                            >Account</CardTitle
                        >
                    </template>
                </CardHeader>

                <CardContent>
                    <form
                        v-if="can('users.update')"
                        class="space-y-5"
                        @submit.prevent="save"
                    >
                        <FormSection title="Details">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <FormField label="Name" :error="form.errors.name">
                                    <Input v-model="form.name" />
                                </FormField>

                                <FormField label="Email" :error="form.errors.email">
                                    <Input v-model="form.email" type="email" />
                                </FormField>

                                <FormField
                                    label="New password"
                                    :error="form.errors.password"
                                    hint="Leave blank to keep the current one."
                                >
                                    <Input v-model="form.password" type="password" />
                                </FormField>

                                <FormField label="Confirm new password">
                                    <Input
                                        v-model="form.password_confirmation"
                                        type="password"
                                    />
                                </FormField>
                            </div>
                        </FormSection>

                        <FormSection title="Roles">
                            <FormField :error="form.errors.roles">
                                <div class="flex flex-wrap gap-2">
                                    <label
                                        v-for="role in roles"
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

                            <p
                                v-if="firstOf('role', 'roles')"
                                class="text-2xs text-danger"
                            >
                                {{ firstOf('role', 'roles') }}
                            </p>
                        </FormSection>

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                size="dense"
                                :disabled="form.processing"
                                >Save changes</Button
                            >
                        </div>
                    </form>

                    <dl v-else class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted-foreground">Email</dt>
                            <dd class="text-2sm">{{ user.email }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Roles</dt>
                            <dd class="text-2sm">
                                {{
                                    user.roles.map((role) => role.name).join(', ') ||
                                    'No role'
                                }}
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card class="self-start">
                <CardHeader>
                    <template #title><CardTitle>Profile</CardTitle></template>
                </CardHeader>

                <CardContent>
                    <div class="mb-4 flex items-center gap-3">
                        <Avatar
                            :name="user.name"
                            class="size-10"
                            :online="user.is_active"
                        />
                        <div class="min-w-0">
                            <p class="truncate text-2sm font-medium">
                                {{ user.name }}
                            </p>
                            <p class="truncate text-2xs text-muted-foreground">
                                {{ user.email }}
                            </p>
                        </div>
                    </div>

                    <dl class="space-y-3">
                        <div class="flex justify-between gap-3">
                            <dt class="text-xs text-muted-foreground">Joined</dt>
                            <dd class="text-2sm">
                                {{ dateTime(user.created_at) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-xs text-muted-foreground">
                                Email verified
                            </dt>
                            <dd class="text-2sm">
                                {{ dateTime(user.email_verified_at) }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-1">
                        <Badge
                            v-for="role in user.roles"
                            :key="role.id"
                            variant="outline"
                            size="sm"
                            >{{ role.name }}</Badge
                        >
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
