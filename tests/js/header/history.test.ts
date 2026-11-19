import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { customRef, nextTick, ref } from 'vue';
import { dragging } from '@/components/documents/config/header/useHandleDrag';
import { useHeaderHistory } from '@/components/documents/config/header/useHeaderHistory';
import type { HeaderLayout } from '@/types';

const layout = (width: number): HeaderLayout => ({
    version: 2,
    border: '#404552',
    logo: {
        enabled: true,
        source: 'company',
        assetId: null,
        side: 'left',
        rows: 1,
        width,
        valign: 'middle',
        padding: 8,
    },
    rows: [],
});

beforeEach(() => vi.useFakeTimers());
afterEach(() => {
    vi.useRealTimers();
    dragging.value = false;
});

describe('undo and redo', () => {
    it('makes one step of a burst of changes and walks back and forth', () => {
        const model = ref(layout(22));
        const history = useHeaderHistory(model);

        model.value = layout(23);
        model.value = layout(24);
        vi.advanceTimersByTime(600);
        model.value = layout(30);
        vi.advanceTimersByTime(600);

        history.undo();
        expect(model.value.logo.width).toBe(24);
        history.undo();
        expect(model.value.logo.width).toBe(22);
        expect(history.canUndo.value).toBe(false);

        history.redo();
        history.redo();
        expect(model.value.logo.width).toBe(30);
        expect(history.canRedo.value).toBe(false);
    });

    it('treats a whole drag as one step, however many moves it had', async () => {
        const model = ref(layout(22));
        const history = useHeaderHistory(model);

        dragging.value = true;
        for (const w of [25, 28, 31, 35]) model.value = layout(w);
        vi.advanceTimersByTime(2000);
        dragging.value = false;
        await nextTick();

        history.undo();
        expect(model.value.logo.width).toBe(22);
        expect(history.canUndo.value).toBe(false);
    });

    it('undoes a change made a moment ago without waiting, and drops redo after a new change', () => {
        const model = ref(layout(22));
        const history = useHeaderHistory(model);

        model.value = layout(25);
        expect(history.canUndo.value).toBe(true);
        history.undo();
        expect(model.value.logo.width).toBe(22);
        expect(history.canRedo.value).toBe(true);

        model.value = layout(40);
        vi.advanceTimersByTime(600);
        expect(history.canRedo.value).toBe(false);
    });
});

describe('with a v-model that answers a tick later', () => {
    it('does not take the undo itself for a new edit, so redo still works', async () => {
        let stored = layout(22);
        let notify: () => void = () => {};
        const model = customRef<HeaderLayout>((track, trigger) => {
            notify = trigger;

            return {
                get: () => {
                    track();

                    return stored;
                },
                set: (value) => {
                    void Promise.resolve().then(() => {
                        stored = value;
                        trigger();
                    });
                },
            };
        });
        const history = useHeaderHistory(model);

        model.value = layout(30);
        await nextTick();
        await nextTick();
        vi.advanceTimersByTime(600);
        history.undo();
        await nextTick();
        await nextTick();
        vi.advanceTimersByTime(600);

        expect(stored.logo.width).toBe(22);
        expect(history.canRedo.value).toBe(true);
        notify();
    });
});
