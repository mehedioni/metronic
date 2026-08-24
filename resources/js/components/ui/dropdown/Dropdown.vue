<script setup lang="ts">
import {
    DropdownMenuContent,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuTrigger,
} from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    align?: 'start' | 'center' | 'end';
    contentClass?: HTMLAttributes['class'];
}>();
</script>

<template>
    <DropdownMenuRoot>
        <DropdownMenuTrigger as-child>
            <slot name="trigger" />
        </DropdownMenuTrigger>

        <DropdownMenuPortal>
            <DropdownMenuContent
                :align="props.align ?? 'end'"
                :side-offset="6"
                :class="
                    cn(
                        'z-50 min-w-44 overflow-hidden rounded-lg border border-border bg-popover p-1 text-popover-foreground shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                        props.contentClass,
                    )
                "
            >
                <slot />
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>
