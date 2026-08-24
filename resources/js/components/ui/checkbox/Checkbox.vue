<script setup lang="ts">
import { CheckIcon, MinusIcon } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    modelValue?: boolean;
    /** Half-checked state for a "select all" box over a partial selection. */
    indeterminate?: boolean;
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
        role="checkbox"
        :aria-checked="indeterminate ? 'mixed' : Boolean(modelValue)"
        :aria-label="ariaLabel"
        :disabled="disabled"
        :class="
            cn(
                'flex size-4 shrink-0 cursor-pointer items-center justify-center rounded border transition-colors focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50',
                modelValue || indeterminate
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-input bg-background',
                props.class,
            )
        "
        @click="toggle"
    >
        <MinusIcon v-if="indeterminate" class="size-3" />
        <CheckIcon v-else-if="modelValue" class="size-3" />
    </button>
</template>
