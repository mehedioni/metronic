import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

export interface TableQueryOptions {
    /** Endpoint the list is served from — always a Wayfinder URL. */
    url: string;
    /** Initial values, normally the `filters` prop the controller sent back. */
    filters?: Record<string, unknown>;
    /** Partial-reload keys, so filtering does not re-send the whole page. */
    only?: string[];
    /** Milliseconds to wait before a typed search reaches the server. */
    debounce?: number;
}

/**
 * Server-driven list state: search, sort, per-page and any extra filters.
 *
 * Filtering, sorting and paging are all the backend's job — the same query
 * runs whether the user typed a search or opened a bookmarked URL, and the
 * results stay consistent across pages. The alternative, sorting the current
 * page in the browser, silently sorts a slice of the data.
 */
export function useTableQuery(options: TableQueryOptions) {
    const initial = options.filters ?? {};

    /**
     * A query bag: keys come from the controller's filter list and values are
     * whatever the URL and the form controls exchange (strings, numbers,
     * booleans). Typed loosely on purpose so a page can bind any filter it
     * has without redeclaring the shape.
     */
    const params = reactive<Record<string, any>>({
        search: (initial.search as string) ?? '',
        sort: (initial.sort as string) ?? '',
        direction: (initial.direction as string) ?? '',
        per_page: (initial.per_page as number) ?? '',
        ...stripKnown(initial),
    });

    const loading = ref(false);
    let timer: ReturnType<typeof setTimeout> | undefined;

    function submit(immediate = false) {
        if (timer) {
            clearTimeout(timer);
        }

        const send = () => {
            router.get(options.url, compact(params), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: options.only,
                onStart: () => (loading.value = true),
                onFinish: () => (loading.value = false),
            });
        };

        if (immediate) {
            send();

            return;
        }

        timer = setTimeout(send, options.debounce ?? 300);
    }

    watch(
        () => ({ ...params }),
        () => submit(),
        { deep: true },
    );

    /**
     * Cycle a column: unsorted -> ascending -> descending -> ascending…
     */
    function toggleSort(column: string) {
        if (params.sort !== column) {
            params.sort = column;
            params.direction = 'asc';

            return;
        }

        params.direction = params.direction === 'asc' ? 'desc' : 'asc';
    }

    function sortState(column: string): 'asc' | 'desc' | null {
        if (params.sort !== column) {
            return null;
        }

        return params.direction === 'asc' ? 'asc' : 'desc';
    }

    function reset() {
        Object.keys(params).forEach((key) => {
            params[key] = typeof params[key] === 'boolean' ? false : '';
        });
    }

    return { params, loading, toggleSort, sortState, reset, submit };
}

/** Everything except the keys this composable owns explicitly. */
function stripKnown(filters: Record<string, unknown>): Record<string, unknown> {
    const owned = ['search', 'sort', 'direction', 'per_page'];

    return Object.fromEntries(
        Object.entries(filters)
            .filter(([key]) => !owned.includes(key))
            .map(([key, value]) => [key, value ?? '']),
    );
}

/** Drop empty values so the URL carries only what is actually filtering. */
function compact(params: Record<string, unknown>): RequestPayload {
    return Object.fromEntries(
        Object.entries(params).filter(
            ([, value]) =>
                value !== '' &&
                value !== null &&
                value !== undefined &&
                value !== false,
        ),
    ) as RequestPayload;
}
