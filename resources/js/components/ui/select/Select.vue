<script setup lang="ts">
import { ChevronDownIcon } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * A native select, styled. Reka's listbox is reserved for the cases that need
 * multi-select or rich rows; for a plain choice the native control keeps
 * keyboard and mobile behaviour for free.
 */
const props = defineProps<{
    modelValue?: string | number | null;
    invalid?: boolean;
    class?: HTMLAttributes['class'];
}>();

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <div class="relative">
        <select
            :value="modelValue ?? ''"
            :class="
                cn(
                    'h-8.5 w-full appearance-none rounded-md border bg-background pe-8 ps-3 text-2sm text-foreground shadow-xs transition-colors focus:outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50',
                    invalid ? 'border-danger' : 'border-input',
                    props.class,
                )
            "
            @change="
                $emit(
                    'update:modelValue',
                    ($event.target as HTMLSelectElement).value,
                )
            "
        >
            <slot />
        </select>

        <ChevronDownIcon
            class="pointer-events-none absolute end-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground"
        />
    </div>
</template>
