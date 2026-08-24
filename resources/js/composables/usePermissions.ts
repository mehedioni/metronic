import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SharedData } from '@/types';

/**
 * Permission checks for the UI, backed by the same permission names the
 * backend enforces. This only hides controls — every action is still
 * authorized server-side by middleware and a policy.
 */
export function usePermissions() {
    const page = usePage<SharedData>();

    const permissions = computed(() => page.props.auth.permissions ?? []);
    const roles = computed(() => page.props.auth.roles ?? []);
    const isSuperAdmin = computed(() => roles.value.includes('Super Admin'));

    function can(permission: string): boolean {
        return isSuperAdmin.value || permissions.value.includes(permission);
    }

    function canAny(...names: string[]): boolean {
        return names.some((name) => can(name));
    }

    return { permissions, roles, isSuperAdmin, can, canAny };
}
