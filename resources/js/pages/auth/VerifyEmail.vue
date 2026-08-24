<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';

defineProps<{ status?: string | null }>();

const form = useForm({});

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Head title="Verify email" />

    <AuthLayout
        title="Verify your email"
        description="We can send you a fresh verification link."
    >
        <p
            v-if="status === 'verification-link-sent'"
            class="mb-4 rounded-md border border-success/20 bg-success-soft px-3 py-2 text-xs text-success"
        >
            A new verification link has been sent.
        </p>

        <div class="space-y-3">
            <Button
                class="w-full"
                :disabled="form.processing"
                @click="form.post('/email/verification-notification')"
            >
                Resend link
            </Button>

            <Button variant="ghost" class="w-full" @click="logout">
                Sign out
            </Button>
        </div>
    </AuthLayout>
</template>
