<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

defineProps<{ status?: string | null }>();

const form = useForm({});
</script>

<template>
    <Head title="Verify email" />

    <div
        class="flex min-h-svh items-center justify-center bg-background px-4 text-foreground"
    >
        <div
            class="w-full max-w-sm space-y-4 rounded-lg border border-border bg-card p-6"
        >
            <h1 class="text-xl font-semibold">Verify your email</h1>
            <p class="text-sm text-muted-foreground">
                We can send you a fresh verification link.
            </p>
            <p
                v-if="status === 'verification-link-sent'"
                class="text-sm text-emerald-600"
            >
                A new verification link has been sent.
            </p>

            <Button
                :disabled="form.processing"
                @click="form.post('/email/verification-notification')"
            >
                Resend link
            </Button>
        </div>
    </div>
</template>
