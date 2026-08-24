<script setup lang="ts" generic="T extends { id: string }">
import {
    ChevronDownIcon,
    ChevronsUpDownIcon,
    ChevronUpIcon,
} from 'lucide-vue-next';
import { computed, toRef } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { useRowSelection } from '@/composables/useRowSelection';
import { get } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface Column {
    /** Row key, dotted paths allowed ("category.name"). */
    key: string;
    label: string;
    /** Sort key sent to the backend; omit for a column that cannot sort. */
    sort?: string;
    align?: 'start' | 'center' | 'end';
    /** Applied to both the header cell and the body cells. */
    class?: string;
    width?: string;
}

const props = defineProps<{
    columns: Column[];
    rows: T[];
    /** Enables the checkbox column and exposes the selection to the parent. */
    selectable?: boolean;
    loading?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
    /** Current sort, from useTableQuery().sortState. */
    sortState?: (column: string) => 'asc' | 'desc' | null;
}>();

const emit = defineEmits<{
    sort: [column: string];
    'selection-change': [rows: T[]];
}>();

const rows = toRef(props, 'rows');
const selection = useRowSelection<T>(rows);

/**
 * Slot name for a column's cell.
 *
 * Dots are replaced because a Vue slot name containing one is parsed as a
 * directive modifier: `#cell-product.name` would be read as slot `cell-product`
 * with a `name` modifier. So a column keyed "product.name" is rendered by a
 * `#cell-product_name` slot.
 */
function slotName(column: Column): string {
    return `cell-${column.key.replace(/[^a-zA-Z0-9]/g, '_')}`;
}

/** Header and body share the alignment, so it is resolved once per column. */
const alignment = computed(() => (column: Column) => ({
    'text-center': column.align === 'center',
    'text-end': column.align === 'end',
}));

function toggleRow(id: string) {
    selection.toggle(id);
    emit('selection-change', selection.selectedRows.value);
}

function toggleAll() {
    selection.toggleAll();
    emit('selection-change', selection.selectedRows.value);
}

defineExpose({ selection });
</script>

<template>
    <div class="relative">
        <div
            v-if="loading"
            class="pointer-events-none absolute inset-0 z-10 bg-card/60 backdrop-blur-[1px]"
        />

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-start text-xs">
                <thead>
                    <tr
                        class="border-b border-border bg-muted/70 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground"
                    >
                        <th v-if="selectable" class="w-10 px-4 py-3.5 text-center">
                            <div class="flex justify-center">
                                <Checkbox
                                    :model-value="selection.allSelected.value"
                                    :indeterminate="selection.someSelected.value"
                                    aria-label="Select all rows"
                                    @update:model-value="toggleAll"
                                />
                            </div>
                        </th>

                        <th
                            v-for="column in columns"
                            :key="column.key"
                            :style="column.width ? { width: column.width } : undefined"
                            :class="
                                cn(
                                    'px-4 py-3.5 text-start font-semibold',
                                    alignment(column),
                                    column.class,
                                )
                            "
                        >
                            <button
                                v-if="column.sort"
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 transition-colors hover:text-foreground"
                                @click="emit('sort', column.sort)"
                            >
                                <span>{{ column.label }}</span>
                                <ChevronUpIcon
                                    v-if="sortState?.(column.sort) === 'asc'"
                                    class="size-3 text-foreground"
                                />
                                <ChevronDownIcon
                                    v-else-if="sortState?.(column.sort) === 'desc'"
                                    class="size-3 text-foreground"
                                />
                                <ChevronsUpDownIcon
                                    v-else
                                    class="size-3 text-muted-foreground/70"
                                />
                            </button>
                            <span v-else>{{ column.label }}</span>
                        </th>

                        <th
                            v-if="$slots.actions"
                            class="w-16 px-4 py-3.5 text-end font-semibold"
                        >
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-border text-foreground">
                    <tr
                        v-for="row in rows"
                        :key="row.id"
                        class="transition-colors hover:bg-muted/60"
                        :class="
                            selection.isSelected(row.id) ? 'bg-accent/50' : ''
                        "
                    >
                        <td v-if="selectable" class="px-4 py-3 text-center">
                            <div class="flex justify-center">
                                <Checkbox
                                    :model-value="selection.isSelected(row.id)"
                                    aria-label="Select row"
                                    @update:model-value="toggleRow(row.id)"
                                />
                            </div>
                        </td>

                        <td
                            v-for="column in columns"
                            :key="column.key"
                            :class="
                                cn('px-4 py-3', alignment(column), column.class)
                            "
                        >
                            <!-- A page supplies `cell-<key>` to render a column
                                 itself; anything else falls back to the value. -->
                            <slot
                                :name="slotName(column)"
                                :row="row"
                                :value="get(row as Record<string, any>, column.key)"
                            >
                                {{
                                    get(row as Record<string, any>, column.key) ??
                                    '—'
                                }}
                            </slot>
                        </td>

                        <td v-if="$slots.actions" class="px-4 py-3 text-end">
                            <slot name="actions" :row="row" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EmptyState
            v-if="!rows.length"
            :title="emptyTitle"
            :description="emptyDescription"
        >
            <template v-if="$slots['empty-action']" #action>
                <slot name="empty-action" />
            </template>
        </EmptyState>
    </div>
</template>
