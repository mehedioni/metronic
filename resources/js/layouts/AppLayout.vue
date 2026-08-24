<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { MoonIcon, SunIcon } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import type { SharedData } from '@/types';

defineProps<{
    title?: string;
}>();

const page = usePage<SharedData>();
const { resolvedAppearance, updateAppearance } = useAppearance();

function toggleAppearance() {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <header
            class="flex items-center justify-between border-b border-border px-6 py-4"
        >
            <Link href="/" class="text-lg font-semibold">RentMy Admin</Link>

            <div class="flex items-center gap-4">
                <span
                    v-if="page.props.auth.user"
                    class="text-sm text-muted-foreground"
                >
                    {{ page.props.auth.user.name }}
                </span>

                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Toggle theme"
                    @click="toggleAppearance"
                >
                    <SunIcon
                        v-if="resolvedAppearance === 'dark'"
                        class="size-4"
                    />
                    <MoonIcon v-else class="size-4" />
                </Button>
            </div>
        </header>

        <main class="flex-1 px-6 py-8">
            <h1 v-if="title" class="mb-6 text-2xl font-semibold">
                {{ title }}
            </h1>
            <slot />
        </main>
    </div>
</template>
