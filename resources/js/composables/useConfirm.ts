import { reactive } from 'vue';

export type ConfirmOptions = {
    title: string;
    message?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    /** A red confirm button, for deleting or discarding. */
    destructive?: boolean;
};

type State = {
    open: boolean;
    options: ConfirmOptions;
    settle: ((answer: boolean) => void) | null;
};

/** Shared by every page: the one `ConfirmHost` in the layout shows it. */
export const confirmState = reactive<State>({
    open: false,
    options: { title: '' },
    settle: null,
});

/** Closes the dialog with an answer (what the host calls). */
export function answerConfirm(answer: boolean): void {
    const settle = confirmState.settle;

    confirmState.open = false;
    confirmState.settle = null;
    settle?.(answer);
}

/**
 * Asks the person to confirm in the app's own dialog:
 * `if (await confirm({ title: 'Hapus?', destructive: true })) …`.
 * Resolves true only on the confirm button; cancel, Esc and a click outside resolve false.
 */
export function confirm(options: ConfirmOptions): Promise<boolean> {
    if (confirmState.settle) answerConfirm(false);

    return new Promise((resolve) => {
        confirmState.options = options;
        confirmState.settle = resolve;
        confirmState.open = true;
    });
}

export function useConfirm() {
    return { confirm };
}
