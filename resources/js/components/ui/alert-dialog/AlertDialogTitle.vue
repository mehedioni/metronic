<script setup lang="ts">
import { type AlertDialogTitleProps, AlertDialogTitle } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<AlertDialogTitleProps & { class?: HTMLAttributes['class'] }>();
</script>

<template>
    <AlertDialogTitle
        v-bind="props"
        :class="cn('text-base font-semibold text-foreground tracking-tight', props.class)"
    >
        <slot />
    </AlertDialogTitle>
</template>
