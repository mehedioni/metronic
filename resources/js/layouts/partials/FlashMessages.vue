<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2Icon, TriangleAlertIcon, XIcon } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import type { SharedData } from '@/types';

/**
 * Flash toasts, driven by the session flash Laravel already sets after every
 * write. Dismissed on click, and re-shown whenever a new message arrives.
 */
const page = usePage<SharedData>();

const success = ref<string | null>(null);
const error = ref<string | null>(null);

watch(
    () => page.props.flash,
    (flash) => {
        success.value = flash?.success ?? null;
        error.value = flash?.error ?? null;
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div
        v-if="success || error"
        class="pointer-events-none fixed inset-x-0 top-20 z-40 flex flex-col items-center gap-2 px-4"
    >
        <div
            v-if="success"
            class="pointer-events-auto flex max-w-md items-start gap-2.5 rounded-lg border border-success/20 bg-success-soft px-4 py-3 text-[0.8125rem] text-success shadow-lg"
        >
            <CheckCircle2Icon class="mt-px size-4 shrink-0" />
            <p class="flex-1">{{ success }}</p>
            <button
                type="button"
                class="cursor-pointer opacity-70 transition-opacity hover:opacity-100"
                aria-label="Dismiss"
                @click="success = null"
            >
                <XIcon class="size-3.5" />
            </button>
        </div>

        <div
            v-if="error"
            class="pointer-events-auto flex max-w-md items-start gap-2.5 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-[0.8125rem] text-danger shadow-lg"
        >
            <TriangleAlertIcon class="mt-px size-4 shrink-0" />
            <p class="flex-1">{{ error }}</p>
            <button
                type="button"
                class="cursor-pointer opacity-70 transition-opacity hover:opacity-100"
                aria-label="Dismiss"
                @click="error = null"
            >
                <XIcon class="size-3.5" />
            </button>
        </div>
    </div>
</template>
