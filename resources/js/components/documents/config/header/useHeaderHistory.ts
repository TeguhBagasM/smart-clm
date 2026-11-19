import { computed, onBeforeUnmount, ref, watch, type Ref } from 'vue';
import { dragging } from '@/components/documents/config/header/useHandleDrag';
import type { HeaderLayout } from '@/types';

const MAX_STEPS = 50;
const SETTLE_MS = 500;

const copy = (layout: HeaderLayout): HeaderLayout =>
    JSON.parse(JSON.stringify(layout));
const same = (a: HeaderLayout, b: HeaderLayout): boolean =>
    JSON.stringify(a) === JSON.stringify(b);

/**
 * Undo and redo of the letterhead. A step is a burst of changes that settles
 * (typing, clicking a stepper) or one whole drag, never each pointer move.
 */
export function useHeaderHistory(layout: Ref<HeaderLayout>) {
    const past = ref<HeaderLayout[]>([]);
    const future = ref<HeaderLayout[]>([]);
    const pending = ref(false);
    let committed = copy(layout.value);
    let applying = false;
    let timer: ReturnType<typeof setTimeout> | undefined;

    /** Closes the current step, when something changed since the last one. */
    function record(): void {
        clearTimeout(timer);
        pending.value = false;

        if (same(layout.value, committed)) return;

        past.value = [...past.value.slice(-(MAX_STEPS - 1)), committed];
        committed = copy(layout.value);
        future.value = [];
    }

    function apply(next: HeaderLayout): void {
        applying = true;
        layout.value = copy(next);
        applying = false;
    }

    watch(
        layout,
        () => {
            // A value equal to the last step is undo/redo arriving late through v-model, not an edit.
            if (applying || same(layout.value, committed)) return;

            clearTimeout(timer);

            if (dragging.value) return;

            pending.value = true;
            timer = setTimeout(record, SETTLE_MS);
        },
        { flush: 'sync' },
    );
    watch(dragging, (isDragging) => {
        if (!isDragging) record();
    });
    onBeforeUnmount(() => clearTimeout(timer));

    return {
        canUndo: computed(() => past.value.length > 0 || pending.value),
        canRedo: computed(() => future.value.length > 0),
        undo(): void {
            record();

            const previous = past.value[past.value.length - 1];

            if (!previous) return;

            past.value = past.value.slice(0, -1);
            future.value = [...future.value, committed];
            committed = copy(previous);
            apply(previous);
        },
        redo(): void {
            const next = future.value[future.value.length - 1];

            if (!next) return;

            future.value = future.value.slice(0, -1);
            past.value = [...past.value, committed];
            committed = copy(next);
            apply(next);
        },
    };
}
