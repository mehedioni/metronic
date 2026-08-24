<script setup lang="ts">
import { TabsList, TabsRoot, TabsTrigger } from 'reka-ui';

/**
 * Underlined tab strip, as used on the product and customer detail screens.
 */
defineProps<{
    modelValue: string;
    tabs: Array<{ value: string; label: string; count?: number | null }>;
}>();

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <TabsRoot
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', String($event))"
    >
        <TabsList
            class="flex items-center gap-1 overflow-x-auto border-b border-border"
        >
            <TabsTrigger
                v-for="tab in tabs"
                :key="tab.value"
                :value="tab.value"
                class="relative cursor-pointer whitespace-nowrap px-3 py-2.5 text-[0.8125rem] font-medium text-muted-foreground transition-colors hover:text-foreground data-[state=active]:text-foreground data-[state=active]:after:absolute data-[state=active]:after:inset-x-0 data-[state=active]:after:-bottom-px data-[state=active]:after:h-0.5 data-[state=active]:after:bg-primary"
            >
                {{ tab.label }}
                <span
                    v-if="tab.count !== null && tab.count !== undefined"
                    class="ms-1.5 text-[11px] text-muted-foreground"
                >
                    {{ tab.count }}
                </span>
            </TabsTrigger>
        </TabsList>

        <slot />
    </TabsRoot>
</template>
