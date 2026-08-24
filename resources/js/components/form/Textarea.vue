<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    modelValue?: string | null;
    rows?: number;
    invalid?: boolean;
    class?: HTMLAttributes['class'];
}>();

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <textarea
        :value="modelValue ?? ''"
        :rows="rows ?? 3"
        :class="
            cn(
                'w-full rounded-md border bg-background p-3 text-[0.8125rem] text-foreground shadow-xs transition-colors placeholder:text-muted-foreground focus:outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30',
                invalid ? 'border-danger' : 'border-input',
                props.class,
            )
        "
        @input="
            $emit(
                'update:modelValue',
                ($event.target as HTMLTextAreaElement).value,
            )
        "
    />
</template>
