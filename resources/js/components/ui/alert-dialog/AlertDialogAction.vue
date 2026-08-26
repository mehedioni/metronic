<script setup lang="ts">
import { type AlertDialogActionProps, AlertDialogAction } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const props = defineProps<AlertDialogActionProps & { class?: HTMLAttributes['class'] }>();
</script>

<template>
    <AlertDialogAction
        v-bind="props"
        :class="cn(buttonVariants(), props.class)"
    >
        <slot />
    </AlertDialogAction>
</template>
