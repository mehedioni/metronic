<script setup lang="ts">
import type { HTMLAttributes, InputHTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps<{
    modelValue?: string | number | null;
    type?: InputHTMLAttributes['type'];
    /** Renders a leading icon slot and reserves room for it. */
    hasIcon?: boolean;
    invalid?: boolean;
    class?: HTMLAttributes['class'];
}>();

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <div class="relative">
        <span
            v-if="hasIcon"
            class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-muted-foreground [&_svg]:size-4"
        >
            <slot name="icon" />
        </span>

        <input
            v-bind="$attrs"
            :type="type ?? 'text'"
            :value="modelValue ?? ''"
            :class="
                cn(
                    'h-8.5 w-full rounded-md border bg-background px-3 text-2sm text-foreground shadow-xs transition-colors placeholder:text-muted-foreground focus:outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50',
                    hasIcon && 'ps-9',
                    invalid ? 'border-danger' : 'border-input',
                    props.class,
                )
            "
            @input="
                $emit(
                    'update:modelValue',
                    ($event.target as HTMLInputElement).value,
                )
            "
        />

        <span
            v-if="$slots.suffix"
            class="absolute end-2.5 top-1/2 -translate-y-1/2 text-muted-foreground"
        >
            <slot name="suffix" />
        </span>
    </div>
</template>
