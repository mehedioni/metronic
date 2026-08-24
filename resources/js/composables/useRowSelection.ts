import { computed, ref, watch } from 'vue';
import type { Ref } from 'vue';

/**
 * Checkbox selection for a table page.
 *
 * The selection is scoped to the rows currently on screen: it clears when the
 * page changes, because a hidden selection is a bulk action waiting to
 * surprise someone.
 */
export function useRowSelection<T extends { id: string | number }>(
    rows: Ref<T[]>,
) {
    // Ids are uuids in the inventory module and integers for users, so the
    // selection is keyed by either.
    const selected = ref<Set<string | number>>(new Set());

    watch(rows, () => (selected.value = new Set()));

    const count = computed(() => selected.value.size);
    const allSelected = computed(
        () =>
            rows.value.length > 0 && selected.value.size === rows.value.length,
    );
    const someSelected = computed(
        () => selected.value.size > 0 && !allSelected.value,
    );

    function isSelected(id: string | number): boolean {
        return selected.value.has(id);
    }

    function toggle(id: string | number) {
        const next = new Set(selected.value);

        if (next.has(id)) {
            next.delete(id);
        } else {
            next.add(id);
        }

        selected.value = next;
    }

    function toggleAll() {
        selected.value = allSelected.value
            ? new Set()
            : new Set(rows.value.map((row) => row.id));
    }

    function clear() {
        selected.value = new Set();
    }

    /** The selected rows themselves, for a bulk action or an export. */
    const selectedRows = computed(() =>
        rows.value.filter((row) => selected.value.has(row.id)),
    );

    return {
        selected,
        selectedRows,
        count,
        allSelected,
        someSelected,
        isSelected,
        toggle,
        toggleAll,
        clear,
    };
}
