<script setup lang="ts">
import {
    AlertDialogContent,
    type AlertDialogContentEmits,
    type AlertDialogContentProps,
    AlertDialogOverlay,
    AlertDialogPortal,
    useForwardPropsEmits,
} from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<AlertDialogContentProps & { class?: HTMLAttributes['class'] }>();
const emits = defineEmits<AlertDialogContentEmits>();

const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
    <AlertDialogPortal>
        <AlertDialogOverlay
            class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <AlertDialogContent
            v-bind="forwarded"
            :class="
                cn(
                    'fixed left-[50%] top-[50%] z-50 grid w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] gap-4 rounded-xl border border-border bg-card text-card-foreground p-6 shadow-2xl duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-left-1/2 data-[state=closed]:slide-out-to-top-[48%] data-[state=open]:slide-in-from-left-1/2 data-[state=open]:slide-in-from-top-[48%] sm:max-w-lg',
                    props.class,
                )
            "
        >
            <slot />
        </AlertDialogContent>
    </AlertDialogPortal>
</template>
