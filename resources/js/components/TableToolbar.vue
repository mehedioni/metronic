<script setup lang="ts">
import { DownloadIcon, SearchIcon, XIcon } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';

/**
 * Search, filters, page size and export above a list. Every control writes to
 * the shared query object from useTableQuery, so all of it round-trips to the
 * backend and survives a page reload.
 */
const props = defineProps<{
    search?: string;
    searchPlaceholder?: string;
    perPage?: number | string;
    /** Rows currently ticked, shown as a bulk-action count. */
    selectedCount?: number;
    exportable?: boolean;
}>();

defineEmits<{
    'update:search': [value: string];
    'update:perPage': [value: string];
    export: [];
    clear: [];
}>();
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
        <div class="flex flex-wrap items-center gap-2">
            <Input
                :model-value="search"
                :placeholder="searchPlaceholder ?? 'Search'"
                has-icon
                class="w-full sm:w-64"
                @update:model-value="$emit('update:search', $event)"
            >
                <template #icon><SearchIcon /></template>
            </Input>

            <slot name="filters" />

            <Button
                v-if="$slots.filters || search"
                variant="ghost"
                size="dense"
                @click="$emit('clear')"
            >
                <XIcon />
                Clear
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <template v-if="props.selectedCount">
                <span class="text-xs text-muted-foreground">
                    {{ props.selectedCount }} selected
                </span>
                <slot name="bulk" />
            </template>

            <slot name="actions" />

            <Button
                v-if="exportable"
                variant="outline"
                size="dense"
                @click="$emit('export')"
            >
                <DownloadIcon />
                Export
            </Button>

            <Select
                :model-value="String(perPage ?? '')"
                class="w-28"
                @update:model-value="$emit('update:perPage', $event)"
            >
                <option value="">15 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
                <option value="100">100 / page</option>
            </Select>
        </div>
    </div>
</template>
