import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { moveItem } from '@/lib/masterData';
import type { OrderRow } from '@/types';

/**
 * Sets the order of a whole list: a local copy to move rows in (drag or the
 * up/down buttons), sent in one request with every id. If saving fails for any
 * reason (validation, server, network) the original order comes back and the
 * server's message is shown, so the screen never shows an order that was not saved.
 */
export function useReorder(failText: () => string) {
    const order = ref<OrderRow[]>([]);
    const original = ref<OrderRow[]>([]);
    const saving = ref(false);
    const error = ref<string | null>(null);

    const dirty = computed(
        () =>
            order.value.map((row) => row.id).join() !==
            original.value.map((row) => row.id).join(),
    );

    function start(rows: OrderRow[]): void {
        original.value = [...rows];
        order.value = [...rows];
        error.value = null;
    }

    function move(from: number, to: number): void {
        order.value = moveItem(order.value, from, to);
    }

    function revert(): void {
        order.value = [...original.value];
    }

    function save(
        url: string,
        extra: Record<string, unknown>,
        done: () => void,
    ): void {
        if (saving.value || !dirty.value) {
            return;
        }

        const fail = (): void => {
            revert();
            error.value = failText();
        };

        error.value = null;
        saving.value = true;
        router.put(
            url,
            { ids: order.value.map((row) => row.id), ...extra },
            {
                preserveScroll: true,
                onSuccess: done,
                onError: fail,
                onHttpException: fail,
                onNetworkError: fail,
                onFinish: () => (saving.value = false),
            },
        );
    }

    return { order, dirty, saving, error, start, move, revert, save };
}
