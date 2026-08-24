<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    /** Tables and lists sit flush; forms and prose want the default padding. */
    flush?: boolean;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn(flush ? '' : 'p-5', props.class)">
        <slot />
    </div>
</template>
