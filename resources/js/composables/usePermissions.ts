import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Permission } from '@/types';

/**
 * Reads the permissions shared by the server (`auth.permissions`).
 * Use it only to show or hide UI; the server enforces every action.
 */
export function usePermissions() {
    const page = usePage();
    const granted = computed(() => new Set(page.props.auth.permissions ?? []));

    const can = (permission: Permission): boolean =>
        granted.value.has(permission);

    const canAny = (permissions: Permission[]): boolean =>
        permissions.some(can);

    return { can, canAny };
}
