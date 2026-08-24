<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import DataCard from '@/components/DataCard.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import usersRoutes from '@/routes/access/users';

interface UserRecord {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    last_login_at: string | null;
    roles: Array<{ id: number; name: string }>;
}

const props = defineProps<{ user: UserRecord; roles: string[] }>();

const { can } = usePermissions();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    roles: props.user.roles.map((role) => role.name),
});

function toggleActive() {
    router.patch(`/access/users/${props.user.id}/status`, {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="user.name" />

    <AppLayout :title="user.name">
        <div class="grid gap-6 lg:grid-cols-2">
            <DataCard title="Account">
                <template #actions>
                    <Button v-if="can('users.update')" variant="ghost" @click="toggleActive">
                        {{ user.is_active ? 'Deactivate' : 'Activate' }}
                    </Button>
                </template>

                <dl class="space-y-2 p-4 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Email</dt>
                        <dd>{{ user.email }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Active</dt>
                        <dd>{{ user.is_active ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Last login</dt>
                        <dd>{{ user.last_login_at ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Roles</dt>
                        <dd>
                            {{
                                user.roles.map((role) => role.name).join(', ') || '—'
                            }}
                        </dd>
                    </div>
                </dl>
            </DataCard>

            <DataCard
                v-if="can('users.update')"
                title="Edit"
                description="You can only grant roles whose permissions you hold yourself."
            >
                <form
                    class="space-y-3 p-4"
                    @submit.prevent="form.put(usersRoutes.update.url(user.id))"
                >
                    <input
                        v-model="form.name"
                        placeholder="Name"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.email"
                        placeholder="Email"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.password"
                        type="password"
                        placeholder="New password (optional)"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        placeholder="Confirm new password"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />

                    <fieldset class="space-y-1">
                        <legend class="text-sm text-muted-foreground">Roles</legend>
                        <label
                            v-for="role in roles"
                            :key="role"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input v-model="form.roles" type="checkbox" :value="role" />
                            {{ role }}
                        </label>
                    </fieldset>

                    <Button type="submit" :disabled="form.processing">Save</Button>
                    <p
                        v-for="(error, field) in form.errors"
                        :key="field"
                        class="text-sm text-red-500"
                    >
                        {{ error }}
                    </p>
                </form>
            </DataCard>
        </div>
    </AppLayout>
</template>
