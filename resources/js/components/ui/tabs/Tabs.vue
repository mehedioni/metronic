<script setup lang="ts">
import { TabsList, TabsRoot, TabsTrigger } from 'reka-ui';

const model = defineModel<string>({ default: '' });

withDefaults(
    defineProps<{
        tabs: Array<{ value: string; label: string; count?: number | null }>;
        variant?: 'underline' | 'pills';
    }>(),
    {
        variant: 'underline',
    },
);
</script>

<template>
    <TabsRoot
        v-model="model"
        class="flex flex-col"
    >
        <div v-if="variant === 'pills'" class="px-5 pt-4">
            <div class="overflow-x-auto">
                <TabsList
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border/80 bg-muted/80 p-1"
                >
                    <TabsTrigger
                        v-for="item in tabs"
                        :key="item.value"
                        :value="item.value"
                        class="shrink-0 cursor-pointer whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs"
                        @click="model = item.value"
                    >
                        {{ item.label }}
                        <span
                            v-if="item.count !== null && item.count !== undefined"
                            class="ms-1.5 text-2xs"
                        >
                            {{ item.count }}
                        </span>
                    </TabsTrigger>
                </TabsList>
            </div>
        </div>

        <TabsList
            v-else
            class="flex items-center gap-1 overflow-x-auto border-b border-border"
        >
            <TabsTrigger
                v-for="item in tabs"
                :key="item.value"
                :value="item.value"
                class="relative cursor-pointer whitespace-nowrap px-3 py-2.5 text-2sm font-medium text-muted-foreground transition-colors hover:text-foreground data-[state=active]:text-foreground data-[state=active]:after:absolute data-[state=active]:after:inset-x-0 data-[state=active]:after:-bottom-px data-[state=active]:after:h-0.5 data-[state=active]:after:bg-primary"
                @click="model = item.value"
            >
                {{ item.label }}
                <span
                    v-if="item.count !== null && item.count !== undefined"
                    class="ms-1.5 text-2xs text-muted-foreground"
                >
                    {{ item.count }}
                </span>
            </TabsTrigger>
        </TabsList>

        <slot />
    </TabsRoot>
</template>
