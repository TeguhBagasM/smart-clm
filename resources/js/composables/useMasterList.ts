import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { confirm } from '@/composables/useConfirm';
import { impactDialog } from '@/lib/masterData';
import type { MasterInfo } from '@/types';

type Hideable = { id: number; name: string; usage_count?: number };

/**
 * Hiding and showing rows of a master data list. Hiding asks first and says,
 * in the server's words, how many records still use the row; the buttons are
 * off while a request runs, so a double tap sends once.
 */
export function useMasterList(info: () => MasterInfo) {
    const busy = ref<number | null>(null);

    async function setActive(
        row: Hideable,
        hide: boolean,
        url: (row: Hideable) => string,
    ): Promise<void> {
        if (busy.value !== null) {
            return;
        }

        const { title, message } = impactDialog(
            info(),
            { name: row.name, usage_count: row.usage_count ?? 0 },
            hide,
        );

        if (
            !(await confirm({
                title,
                message,
                confirmLabel: hide ? 'Sembunyikan' : 'Tampilkan',
                destructive: hide,
            }))
        ) {
            return;
        }

        busy.value = row.id;
        router.patch(
            url(row),
            {},
            {
                preserveScroll: true,
                onFinish: () => (busy.value = null),
            },
        );
    }

    return { busy, setActive };
}
