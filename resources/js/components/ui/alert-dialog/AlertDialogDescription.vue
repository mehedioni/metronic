<script setup lang="ts">
import { type AlertDialogDescriptionProps, AlertDialogDescription } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<AlertDialogDescriptionProps & { class?: HTMLAttributes['class'] }>();
</script>

<template>
    <AlertDialogDescription
        v-bind="props"
        :class="cn('text-sm text-muted-foreground leading-relaxed', props.class)"
    >
        <slot />
    </AlertDialogDescription>
</template>
