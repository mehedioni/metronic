<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout title="Dashboard">
        <div
            class="max-w-2xl space-y-4 rounded-lg border border-border bg-card p-6 text-card-foreground"
        >
            <p class="text-muted-foreground">
                Vue 3 + Inertia + Tailwind CSS 4 are wired up. This page renders
                through
                <code class="rounded bg-muted px-1 py-0.5 text-sm"
                    >resources/js/pages/Dashboard.vue</code
                >
                via
                <code class="rounded bg-muted px-1 py-0.5 text-sm"
                    >routes/web.php</code
                >.
            </p>

            <Button>It works</Button>
        </div>
    </AppLayout>
</template>
