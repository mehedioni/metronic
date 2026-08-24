import { onMounted, onUnmounted, ref } from 'vue';

const COLLAPSED_KEY = 'sidebar.collapsed';

/** Shared so the topbar's toggle and the sidebar itself agree on one state. */
const collapsed = ref(false);
const mobileOpen = ref(false);

/**
 * Sidebar state: collapsed rail on desktop, drawer on mobile.
 *
 * The collapsed choice persists per browser, because it is a workspace
 * preference rather than something the server needs to know.
 */
export function useSidebar() {
    onMounted(() => {
        try {
            collapsed.value = localStorage.getItem(COLLAPSED_KEY) === '1';
        } catch {
            // Private mode or blocked storage: the default is fine.
        }
    });

    function toggleCollapsed() {
        collapsed.value = !collapsed.value;

        try {
            localStorage.setItem(COLLAPSED_KEY, collapsed.value ? '1' : '0');
        } catch {
            // Non-fatal: the preference just will not survive a reload.
        }
    }

    function openMobile() {
        mobileOpen.value = true;
    }

    function closeMobile() {
        mobileOpen.value = false;
    }

    /** One button drives both behaviours, as in the design. */
    function toggle() {
        if (typeof window !== 'undefined' && window.innerWidth < 1024) {
            mobileOpen.value = !mobileOpen.value;

            return;
        }

        toggleCollapsed();
    }

    return {
        collapsed,
        mobileOpen,
        toggle,
        toggleCollapsed,
        openMobile,
        closeMobile,
    };
}

/**
 * Registers the shortcuts the design advertises: Cmd/Ctrl+K focuses search,
 * Escape closes the mobile drawer.
 */
export function useSidebarShortcuts(onSearch?: () => void) {
    function handle(event: KeyboardEvent) {
        if (
            (event.metaKey || event.ctrlKey) &&
            event.key.toLowerCase() === 'k'
        ) {
            event.preventDefault();
            onSearch?.();

            return;
        }

        if (event.key === 'Escape') {
            mobileOpen.value = false;
        }
    }

    onMounted(() => window.addEventListener('keydown', handle));
    onUnmounted(() => window.removeEventListener('keydown', handle));
}
