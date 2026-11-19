import {
    moveStep,
    removeStep,
    disconnect,
} from '@/components/documents/wizard/flow/flowOps';
import type { FlowEditor } from '@/components/documents/wizard/flow/useFlowEditor';

const ARROWS: Record<string, [number, number]> = {
    ArrowLeft: [-1, 0],
    ArrowRight: [1, 0],
    ArrowUp: [0, -1],
    ArrowDown: [0, 1],
};

/** Keyboard on the focused canvas: undo/redo, Esc, Delete, Enter to type, arrows to move. */
export function useCanvasKeys(editor: FlowEditor) {
    const { selection, selectedStep, editing, linking, apply, undo, redo } =
        editor;

    return function key(event: KeyboardEvent): void {
        const mod = event.metaKey || event.ctrlKey;
        const current = selection.value;
        const step = selectedStep.value;

        if (mod && event.key.toLowerCase() === 'z') {
            (event.shiftKey ? redo : undo)();
        } else if (mod && event.key.toLowerCase() === 'y') {
            redo();
        } else if (event.key === 'Escape') {
            if (linking.value) {
                linking.value = null;
            } else {
                selection.value = null;
            }
        } else if (
            (event.key === 'Delete' || event.key === 'Backspace') &&
            current
        ) {
            apply(
                current.kind === 'step'
                    ? removeStep(current.id)
                    : disconnect(current.from, current.link),
            );
            selection.value = null;
        } else if (event.key === 'Enter' && step) {
            editing.value = step.id;
        } else if (ARROWS[event.key] && step) {
            const [dl, dr] = ARROWS[event.key];
            apply(moveStep(step.id, step.lane + dl, step.row + dr));
        } else {
            return;
        }

        event.preventDefault();
    };
}
