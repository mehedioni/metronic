import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Errors Inertia shares on the page rather than on a form.
 *
 * Domain rule violations (restricted deletion, insufficient stock, an illegal
 * status change) come back as field errors keyed by the rule — "inventory",
 * "quantity", "status", "parent_id" — see the module's exception classes.
 * Those keys often do not match any input on screen, so an action like
 * "delete" has nowhere to show them without reading them from here.
 */
export function usePageErrors() {
    const page = usePage();

    const errors = computed(
        () => (page.props.errors ?? {}) as Record<string, string>,
    );

    /** First message among the given keys, for a modal with one error line. */
    function firstOf(...keys: string[]): string | null {
        for (const key of keys) {
            if (errors.value[key]) {
                return errors.value[key];
            }
        }

        return null;
    }

    return { errors, firstOf };
}
