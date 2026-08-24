<script setup lang="ts">
import { XIcon } from 'lucide-vue-next';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    open: boolean;
    title?: string;
    description?: string;
    /** Widths the design uses: a form dialog, or a wide detail dialog. */
    size?: 'sm' | 'default' | 'lg' | 'xl';
    class?: HTMLAttributes['class'];
}>();

defineEmits<{ 'update:open': [value: boolean] }>();

const widths = {
    sm: 'max-w-sm',
    default: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
};
</script>

<template>
    <DialogRoot
        :open="open"
        @update:open="$emit('update:open', $event)"
    >
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />

            <DialogContent
                :class="
                    cn(
                        'fixed start-1/2 top-1/2 z-50 flex max-h-[90vh] w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-2xl data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                        widths[props.size ?? 'default'],
                        props.class,
                    )
                "
            >
                <header
                    class="flex items-start justify-between gap-4 border-b border-dashed border-border px-5 py-4"
                >
                    <div class="min-w-0">
                        <DialogTitle class="text-sm font-semibold">
                            {{ title }}
                        </DialogTitle>
                        <DialogDescription
                            v-if="description"
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            {{ description }}
                        </DialogDescription>
                    </div>

                    <DialogClose
                        class="flex size-7 shrink-0 cursor-pointer items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        aria-label="Close"
                    >
                        <XIcon class="size-4" />
                    </DialogClose>
                </header>

                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                    <slot />
                </div>

                <footer
                    v-if="$slots.footer"
                    class="flex flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-3.5"
                >
                    <slot name="footer" />
                </footer>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
