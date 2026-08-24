<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

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

    <div
        class="flex min-h-svh items-center justify-center bg-background px-4 text-foreground"
    >
        <div
            class="w-full max-w-sm space-y-6 rounded-lg border border-border bg-card p-6"
        >
            <div>
                <h1 class="text-xl font-semibold">Sign in</h1>
                <p class="text-sm text-muted-foreground">
                    RentMy Admin — inventory management
                </p>
            </div>

            <p v-if="status" class="text-sm text-emerald-600">{{ status }}</p>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1">
                    <label class="text-sm" for="email">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        required
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <p v-if="form.errors.email" class="text-sm text-red-500">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div class="space-y-1">
                    <label class="text-sm" for="password">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                    <p v-if="form.errors.password" class="text-sm text-red-500">
                        {{ form.errors.password }}
                    </p>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.remember" type="checkbox" />
                    Remember me
                </label>

                <Button type="submit" class="w-full" :disabled="form.processing"
                    >Sign in</Button
                >
            </form>

            <a
                v-if="canResetPassword"
                href="/forgot-password"
                class="block text-sm text-muted-foreground underline"
                >Forgot your password?</a
            >
        </div>
    </div>
</template>
