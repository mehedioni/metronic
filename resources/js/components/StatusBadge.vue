<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { humanize, statusVariant } from '@/lib/status';

const props = defineProps<{
    status: string | null | undefined;
    size?: 'sm' | 'default' | 'lg';
}>();

const variant = computed(() => statusVariant(props.status));
</script>

<template>
    <Badge :variant="variant" :size="size">{{ humanize(status) }}</Badge>
</template>
