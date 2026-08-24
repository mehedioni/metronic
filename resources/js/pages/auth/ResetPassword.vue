<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/AuthLayout.vue';

const props = defineProps<{ email: string; token: string }>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});
</script>

<template>
    <Head title="Reset password" />

    <AuthLayout title="Choose a new password">
        <form
            class="space-y-4"
            @submit.prevent="
                form.post('/reset-password', {
                    onFinish: () =>
                        form.reset('password', 'password_confirmation'),
                })
            "
        >
            <FormField label="Email" :error="form.errors.email">
                <Input
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    :invalid="Boolean(form.errors.email)"
                />
            </FormField>

            <FormField
                label="New password"
                :error="form.errors.password"
                required
            >
                <Input
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    :invalid="Boolean(form.errors.password)"
                    required
                />
            </FormField>

            <FormField
                label="Confirm new password"
                :error="form.errors.password_confirmation"
                required
            >
                <Input
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                />
            </FormField>

            <Button type="submit" class="w-full" :disabled="form.processing">
                Save new password
            </Button>
        </form>
    </AuthLayout>
</template>
