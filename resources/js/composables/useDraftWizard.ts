import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import type { InjectionKey } from 'vue';
import { inject, provide, reactive, ref } from 'vue';
import type { ChoiceOption, Draft } from '@/types';

export type DraftKey =
    | 'language'
    | 'sections'
    | 'flow'
    | 'appendix'
    | 'clauses'
    | 'signers';

export type DraftStatus = 'saved' | 'dirty' | 'saving' | 'error' | 'conflict';

export type DraftState = Pick<
    Draft,
    | 'language_mode'
    | 'sections'
    | 'flow'
    | 'appendix'
    | 'clause_ids'
    | 'signers'
    | 'extra_reviewers'
> & { flowImage: File | null };

export type WizardOptions = {
    users: ChoiceOption[];
    roles: ChoiceOption[];
    clauses: ChoiceOption[];
};

/** The part of the request that carries one piece of the draft, in the names of the API. */
function payloadFor(key: DraftKey, s: DraftState): Record<string, unknown> {
    switch (key) {
        case 'language':
            return { languageMode: s.language_mode };
        case 'sections':
            // Tujuan and Ruang Lingkup now live in the sections: clear the old fields so nothing prints twice.
            return { sections: s.sections, purpose: '', scope: '' };
        case 'flow':
            return {
                flowMode: s.flow.mode,
                flowDisplayStyle: s.flow.style,
                flowLanes: s.flow.lanes,
                flowSteps: s.flow.steps,
                ...(s.flowImage ? { flowImage: s.flowImage } : {}),
            };
        case 'appendix':
            return {
                appendix: s.appendix.filter((item) => item.label && item.url),
            };
        case 'clauses':
            return { clauseIds: s.clause_ids };
        case 'signers':
            // Both together: the server rebuilds the whole signing sheet.
            return {
                signatureAssignments: s.signers.map(
                    (signer) => signer.assigned,
                ),
                extraReviewers: s.extra_reviewers
                    .filter((reviewer) => reviewer.label.trim())
                    .map((reviewer) => ({
                        label: reviewer.label,
                        role: reviewer.role || null,
                        assignedUserId: reviewer.assigned,
                    })),
            };
    }
}

/**
 * Local copy of a draft that saves itself: every change marks a piece dirty
 * and, after a pause, only the dirty pieces are sent with the revision the
 * page last saw. A refusal (someone saved since) turns the status to
 * `conflict` and nothing is overwritten.
 */
export function useDraftWizard(initial: Draft, url: string) {
    const state = reactive<DraftState>({
        ...(JSON.parse(JSON.stringify(initial)) as Draft),
        flowImage: null,
    });
    const status = ref<DraftStatus>('saved');
    const dirty = new Set<DraftKey>();
    let revision = initial.revision;
    let running: Promise<void> | null = null;

    function send(): Promise<void> {
        const keys = [...dirty];
        dirty.clear();
        status.value = 'saving';
        const payload = Object.assign(
            { revision },
            ...keys.map((key) => payloadFor(key, state)),
        );

        return new Promise((resolve) => {
            router.patch(url, payload, {
                preserveState: true,
                preserveScroll: true,
                preserveUrl: true,
                only: ['draft'],
                onSuccess: (page) => {
                    const next = (page.props.draft as Draft).revision;

                    if (next > revision) {
                        revision = next;
                        status.value = dirty.size ? 'dirty' : 'saved';
                        if (keys.includes('flow')) state.flowImage = null;
                    } else {
                        status.value = 'conflict';
                    }
                },
                onError: () => {
                    keys.forEach((key) => dirty.add(key));
                    status.value = 'error';
                },
                onFinish: () => resolve(),
            });
        });
    }

    /** Sends what is dirty now, and waits for any save in progress. */
    async function flush(): Promise<void> {
        while (running || (dirty.size > 0 && status.value !== 'conflict')) {
            if (running) {
                await running;
                continue;
            }

            running = send().finally(() => (running = null));
            await running;

            if (status.value === 'error' || status.value === 'conflict') break;
        }
    }

    const later = useDebounceFn(() => void flush(), 900);

    function touch(key: DraftKey): void {
        dirty.add(key);
        if (status.value !== 'conflict') status.value = 'dirty';
        void later();
    }

    return { state, status, touch, flush };
}

export type DraftWizard = ReturnType<typeof useDraftWizard> & {
    options: WizardOptions;
    documentId: string;
    versionId: string;
};

const key: InjectionKey<DraftWizard> = Symbol('draft-wizard');

export function provideDraftWizard(wizard: DraftWizard): void {
    provide(key, wizard);
}

export function useWizard(): DraftWizard {
    const wizard = inject(key);

    if (!wizard) throw new Error('useWizard must be used inside the wizard.');

    return wizard;
}
