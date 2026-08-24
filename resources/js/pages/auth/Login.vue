<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/AuthLayout.vue';

defineProps<{
    canResetPassword: boolean;
    status?: string | null;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Sign in" />

    <AuthLayout title="Sign in" description="Use your store account.">
        <p
            v-if="status"
            class="mb-4 rounded-md border border-success/20 bg-success-soft px-3 py-2 text-xs text-success"
        >
            {{ status }}
        </p>

        <form class="space-y-4" @submit.prevent="submit">
            <FormField label="Email" :error="form.errors.email" required>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    :invalid="Boolean(form.errors.email)"
                    required
                />
            </FormField>

            <FormField label="Password" :error="form.errors.password" required>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    :invalid="Boolean(form.errors.password)"
                    required
                />
            </FormField>

            <div class="flex items-center justify-between gap-3">
                <label class="flex cursor-pointer items-center gap-2 text-xs">
                    <Checkbox
                        v-model="form.remember"
                        aria-label="Remember me"
                    />
                    Remember me
                </label>

                <Link
                    v-if="canResetPassword"
                    href="/forgot-password"
                    class="text-xs text-muted-foreground transition-colors hover:text-foreground"
                    >Forgot password?</Link
                >
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                Sign in
            </Button>
        </form>
    </AuthLayout>
</template>
