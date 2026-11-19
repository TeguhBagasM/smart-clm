import { ref } from 'vue';

/** True while a handle is being dragged: what is under the pointer must not change the focus meanwhile. */
export const dragging = ref(false);

/**
 * Runs a pointer drag from `event`: `onMove` gets how far the pointer went in
 * points (screen pixels scaled to the canvas width) and whether Shift is held.
 */
export function startHandleDrag(
    event: PointerEvent,
    canvas: HTMLElement,
    canvasWidthPt: number,
    onMove: (dxPt: number, dyPt: number, shift: boolean) => void,
): void {
    event.preventDefault();
    dragging.value = true;

    const scale = canvasWidthPt / canvas.getBoundingClientRect().width;
    const [startX, startY] = [event.clientX, event.clientY];
    const move = (e: PointerEvent): void =>
        onMove(
            (e.clientX - startX) * scale,
            (e.clientY - startY) * scale,
            e.shiftKey,
        );
    const stop = (): void => {
        dragging.value = false;
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', stop);
        window.removeEventListener('pointercancel', stop);
    };

    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', stop);
    window.addEventListener('pointercancel', stop);
}
