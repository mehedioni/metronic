<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/AuthLayout.vue';

defineProps<{ status?: string | null }>();

const form = useForm({ email: '' });
</script>

<template>
    <Head title="Forgot password" />

    <AuthLayout
        title="Reset your password"
        description="We will email you a link to choose a new one."
    >
        <p
            v-if="status"
            class="mb-4 rounded-md border border-success/20 bg-success-soft px-3 py-2 text-xs text-success"
        >
            {{ status }}
        </p>

        <form class="space-y-4" @submit.prevent="form.post('/forgot-password')">
            <FormField label="Email" :error="form.errors.email" required>
                <Input
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    :invalid="Boolean(form.errors.email)"
                    required
                />
            </FormField>

            <Button type="submit" class="w-full" :disabled="form.processing">
                Email reset link
            </Button>

            <Link
                href="/login"
                class="block text-center text-xs text-muted-foreground transition-colors hover:text-foreground"
                >Back to sign in</Link
            >
        </form>
    </AuthLayout>
</template>
