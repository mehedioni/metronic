<script setup lang="ts">
import { DropdownMenuItem } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    /** Destructive actions read in the danger colour, as in the design. */
    destructive?: boolean;
    disabled?: boolean;
    class?: HTMLAttributes['class'];
}>();

defineEmits<{ select: [event: Event] }>();
</script>

<template>
    <DropdownMenuItem
        :disabled="disabled"
        :class="
            cn(
                'flex cursor-pointer select-none items-center gap-2 rounded-md px-2.5 py-1.5 text-2sm outline-none data-[highlighted]:bg-accent data-[disabled]:pointer-events-none data-[highlighted]:text-accent-foreground data-[disabled]:opacity-50 [&_svg]:size-3.5 [&_svg]:shrink-0 [&_svg]:text-muted-foreground',
                destructive &&
                    'text-danger data-[highlighted]:bg-danger-soft data-[highlighted]:text-danger [&_svg]:text-danger',
                props.class,
            )
        "
        @select="$emit('select', $event)"
    >
        <slot />
    </DropdownMenuItem>
</template>
