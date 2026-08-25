<script setup lang="ts">
import {
    TooltipContent,
    TooltipPortal,
    TooltipProvider,
    TooltipRoot,
    TooltipTrigger,
} from 'reka-ui';

defineProps<{
    text: string;
    side?: 'top' | 'right' | 'bottom' | 'left';
}>();
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <TooltipRoot>
            <TooltipTrigger as-child>
                <slot />
            </TooltipTrigger>

            <TooltipPortal>
                <TooltipContent
                    :side="side ?? 'top'"
                    :side-offset="6"
                    class="z-50 rounded-md border border-border bg-popover px-2 py-1 text-2xs font-medium text-popover-foreground shadow-md data-[state=delayed-open]:animate-in data-[state=delayed-open]:fade-in-0"
                >
                    {{ text }}
                </TooltipContent>
            </TooltipPortal>
        </TooltipRoot>
    </TooltipProvider>
</template>
