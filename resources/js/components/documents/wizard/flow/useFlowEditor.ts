import { computed, ref } from 'vue';
import { placeSteps } from '@/components/documents/wizard/flow/flowGraph';
import { layoutFlow } from '@/components/documents/wizard/flow/flowLayout';
import type {
    FlowEdit,
    LinkKind,
} from '@/components/documents/wizard/flow/flowOps';
import { normalizeOrder } from '@/components/documents/wizard/flow/flowOps';
import { useWizard } from '@/composables/useDraftWizard';
import type { DraftFlow } from '@/types';

export type Selection =
    | { kind: 'step'; id: string }
    | { kind: 'edge'; from: string; link: LinkKind }
    | null;

const HISTORY = 50;

/**
 * The canvas editor over the wizard's flow: every edit goes through `apply`,
 * which writes the rows and lanes down, renumbers, records undo history and
 * marks the flow dirty so the wizard saves it.
 */
export function useFlowEditor() {
    const { state, touch } = useWizard();
    const selection = ref<Selection>(null);
    /** Step whose text is being typed in place. */
    const editing = ref<string | null>(null);
    /** Step waiting for a click on the step it should link to. */
    const linking = ref<string | null>(null);
    const past = ref<DraftFlow[]>([]);
    const future = ref<DraftFlow[]>([]);
    let lastMerge: string | null = null;

    const layout = computed(() =>
        layoutFlow(state.flow.lanes, state.flow.steps),
    );

    const placed = (): DraftFlow => ({
        ...state.flow,
        steps: placeSteps(state.flow.lanes, state.flow.steps),
    });

    function write(flow: DraftFlow): void {
        state.flow = flow;
        touch('flow');
    }

    /**
     * Runs an edit; refused edits change nothing and return false. Edits with
     * the same `merge` key in a row (typing) share one undo step.
     */
    function apply(edit: FlowEdit, merge?: string): boolean {
        const next = edit(placed());

        if (!next) {
            return false;
        }

        if (!merge || merge !== lastMerge) {
            past.value = [...past.value.slice(-(HISTORY - 1)), state.flow];
            future.value = [];
        }

        lastMerge = merge ?? null;
        write(normalizeOrder(next));

        return true;
    }

    function travel(from: typeof past, to: typeof future): void {
        const target = from.value.at(-1);

        if (!target) {
            return;
        }

        from.value = from.value.slice(0, -1);
        to.value = [...to.value, state.flow];
        lastMerge = null;
        write(target);

        if (
            selection.value?.kind === 'step' &&
            !target.steps.some(
                (step) => step.id === (selection.value as { id: string }).id,
            )
        ) {
            selection.value = null;
        }
    }

    const selectedStep = computed(() => {
        const current = selection.value;

        return current?.kind === 'step'
            ? layout.value.steps.find((step) => step.id === current.id)
            : undefined;
    });

    return {
        state,
        layout,
        selection,
        selectedStep,
        editing,
        linking,
        apply,
        undo: () => travel(past, future),
        redo: () => travel(future, past),
        canUndo: computed(() => past.value.length > 0),
        canRedo: computed(() => future.value.length > 0),
    };
}

export type FlowEditor = ReturnType<typeof useFlowEditor>;
