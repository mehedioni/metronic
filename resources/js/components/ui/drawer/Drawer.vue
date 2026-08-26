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

/**
 * Side panel anchored to the end of the viewport.
 *
 * Every overlay in this design is one of these — forms, detail views and
 * confirmations alike. It floats inset from the viewport edges with rounded
 * corners rather than running edge to edge, and slides in from the end side.
 *
 * Built on the dialog primitive so focus trapping, escape handling and scroll
 * locking come from one place.
 */
const props = defineProps<{
    open: boolean;
    title?: string;
    description?: string;
    /**
     * Panel width, matching the design's sizes: a confirmation, a single
     * column form, a two column form, and a full detail view.
     */
    size?: 'sm' | 'default' | 'md' | 'lg' | 'xl';
    /**
     * Drop the body's padding so the content can reach the panel edges. For a
     * split layout whose divider has to run from the header to the footer,
     * which it cannot do inside a padded box.
     */
    flush?: boolean;
    class?: HTMLAttributes['class'];
}>();

defineEmits<{ 'update:open': [value: boolean] }>();

const widths = {
    sm: 'lg:w-[420px]',
    default: 'lg:w-[500px]',
    md: 'lg:w-[820px]',
    lg: 'lg:w-[1080px]',
    xl: 'lg:w-[1160px]',
};
</script>

<template>
    <DialogRoot :open="open" @update:open="$emit('update:open', $event)">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />

            <DialogContent
                :class="
                    cn(
                        'fixed inset-5 start-auto z-60 flex h-auto w-full max-w-[calc(100vw-40px)] flex-col overflow-hidden rounded-lg border border-border bg-card text-card-foreground shadow-2xl duration-300 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right',
                        widths[props.size ?? 'default'],
                        props.class,
                    )
                "
            >
                <header
                    class="flex shrink-0 items-start justify-between gap-4 border-b border-border bg-muted/40 px-5 py-3.5"
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

                    <div class="flex shrink-0 items-center gap-2">
                        <slot name="header-actions" />

                        <DialogClose
                            class="flex size-7 cursor-pointer items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            aria-label="Close"
                        >
                            <XIcon class="size-4" />
                        </DialogClose>
                    </div>
                </header>

                <div
                    class="min-h-0 flex-1 overflow-y-auto"
                    :class="flush ? '' : 'px-5 py-4'"
                >
                    <slot />
                </div>

                <footer
                    v-if="$slots.footer"
                    class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-3.5"
                >
                    <slot name="footer" />
                </footer>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
