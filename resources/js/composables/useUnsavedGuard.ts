import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';
import { confirm } from '@/composables/useConfirm';

/**
 * Warns before leaving a form that has unsaved changes. Following a link (a GET
 * visit) asks in the app's own dialog; the visit is held, then repeated if the
 * person chooses to leave. Closing or reloading the tab still uses the browser's
 * own prompt (`beforeunload`): a page cannot replace that dialog or its text.
 * Saving is a POST or PUT, so it is never held back.
 * `snapshot` returns everything the form holds; it is compared with how it was on arrival.
 */
export function useUnsavedGuard(snapshot: () => unknown): void {
    const initial = JSON.stringify(snapshot());
    const isDirty = (): boolean => JSON.stringify(snapshot()) !== initial;
    let leaving = false;

    const warn = (event: BeforeUnloadEvent): void => {
        if (!isDirty()) return;

        event.preventDefault();
        event.returnValue = '';
    };
    let stop: (() => void) | undefined;

    onMounted(() => {
        window.addEventListener('beforeunload', warn);
        stop = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (
                leaving ||
                visit.method !== 'get' ||
                visit.only.length > 0 ||
                !isDirty()
            )
                return;

            void confirm({
                title: 'Tinggalkan halaman ini?',
                message: 'Perubahan yang belum disimpan akan hilang.',
                confirmLabel: 'Tinggalkan',
                cancelLabel: 'Tetap di sini',
                destructive: true,
            }).then((leave) => {
                if (!leave) return;

                leaving = true;
                router.visit(visit.url, {
                    method: 'get',
                    preserveScroll: visit.preserveScroll,
                    preserveState: visit.preserveState,
                    replace: visit.replace,
                });
            });

            return false;
        });
    });
    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', warn);
        stop?.();
    });
}
