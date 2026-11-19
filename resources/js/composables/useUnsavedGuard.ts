import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Warns before leaving a form that has unsaved changes: closing the tab, and
 * following a link (a GET visit). Saving is a POST or PUT, so it is never held back.
 * `snapshot` returns everything the form holds; it is compared with how it was on arrival.
 */
export function useUnsavedGuard(snapshot: () => unknown): void {
    const initial = JSON.stringify(snapshot());
    const isDirty = (): boolean => JSON.stringify(snapshot()) !== initial;

    const warn = (event: BeforeUnloadEvent): void => {
        if (!isDirty()) return;

        event.preventDefault();
        event.returnValue = '';
    };
    let stop: (() => void) | undefined;

    onMounted(() => {
        window.addEventListener('beforeunload', warn);
        stop = router.on('before', (event) => {
            if (event.detail.visit.method !== 'get' || !isDirty()) return;

            return window.confirm(
                'Perubahan belum disimpan. Tinggalkan halaman ini?',
            );
        });
    });
    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', warn);
        stop?.();
    });
}
