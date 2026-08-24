<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    modelValue?: boolean;
    disabled?: boolean;
    ariaLabel?: string;
    class?: HTMLAttributes['class'];
}>();

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();

function toggle() {
    if (props.disabled) {
        return;
    }

    emit('update:modelValue', !props.modelValue);
}
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="Boolean(modelValue)"
        :aria-label="ariaLabel"
        :disabled="disabled"
        :class="
            cn(
                'relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border border-transparent transition-colors focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50',
                modelValue ? 'bg-primary' : 'bg-secondary',
                props.class,
            )
        "
        @click="toggle"
    >
        <span
            class="pointer-events-none block size-4 rounded-full bg-background shadow transition-transform"
            :class="modelValue ? 'translate-x-4.5' : 'translate-x-0.5'"
        />
    </button>
</template>
