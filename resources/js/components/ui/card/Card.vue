<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <section
        :class="
            cn(
                'rounded-xl border border-border bg-card text-card-foreground shadow-[0_1px_2px_0_rgb(0_0_0/0.03)] transition-shadow hover:shadow-[0_4px_12px_0_rgb(0_0_0/0.05)]',
                props.class,
            )
        "
    >
        <slot />
    </section>
</template>
