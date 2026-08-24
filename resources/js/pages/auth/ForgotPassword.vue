<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

defineProps<{ status?: string | null }>();

const form = useForm({ email: '' });
</script>

<template>
    <Head title="Forgot password" />

    <div
        class="flex min-h-svh items-center justify-center bg-background px-4 text-foreground"
    >
        <div
            class="w-full max-w-sm space-y-6 rounded-lg border border-border bg-card p-6"
        >
            <h1 class="text-xl font-semibold">Reset your password</h1>
            <p v-if="status" class="text-sm text-emerald-600">{{ status }}</p>

            <form
                class="space-y-4"
                @submit.prevent="form.post('/forgot-password')"
            >
                <div class="space-y-1">
                    <label class="text-sm" for="email">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <p v-if="form.errors.email" class="text-sm text-red-500">
                        {{ form.errors.email }}
                    </p>
                </div>

                <Button type="submit" class="w-full" :disabled="form.processing"
                    >Email reset link</Button
                >
            </form>

            <a
                href="/login"
                class="block text-sm text-muted-foreground underline"
                >Back to sign in</a
            >
        </div>
    </div>
</template>
