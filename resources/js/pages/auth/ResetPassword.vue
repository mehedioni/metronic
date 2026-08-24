<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

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

    <div
        class="flex min-h-svh items-center justify-center bg-background px-4 text-foreground"
    >
        <div
            class="w-full max-w-sm space-y-6 rounded-lg border border-border bg-card p-6"
        >
            <h1 class="text-xl font-semibold">Choose a new password</h1>

            <form
                class="space-y-4"
                @submit.prevent="form.post('/reset-password')"
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

                <div class="space-y-1">
                    <label class="text-sm" for="password">New password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <p v-if="form.errors.password" class="text-sm text-red-500">
                        {{ form.errors.password }}
                    </p>
                </div>

                <div class="space-y-1">
                    <label class="text-sm" for="password_confirmation"
                        >Confirm password</label
                    >
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                </div>

                <Button type="submit" class="w-full" :disabled="form.processing"
                    >Reset password</Button
                >
            </form>
        </div>
    </div>
</template>
