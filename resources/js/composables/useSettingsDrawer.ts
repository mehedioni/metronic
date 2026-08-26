import { ref } from 'vue';

export type SettingsTab = 'general' | 'profile';

/**
 * Shared so the sidebar can open the drawer the layout renders.
 *
 * Settings is a drawer over whichever page the user is on, not a destination —
 * the same reason the reference design puts it in a sheet. There is therefore
 * no route to visit and nothing to restore on reload.
 */
const open = ref(false);
const tab = ref<SettingsTab>('general');

export function useSettingsDrawer() {
    function openSettings(next: SettingsTab = 'general') {
        tab.value = next;
        open.value = true;
    }

    function closeSettings() {
        open.value = false;
    }

    return { open, tab, openSettings, closeSettings };
}
