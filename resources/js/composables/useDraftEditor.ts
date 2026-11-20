import { router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import TemplateDraftController from '@/actions/App/Http/Controllers/Templates/TemplateDraftController';
import {
    draftPayload,
    type DraftState,
    stateFromVersion,
} from '@/lib/contractLibrary';
import { libraryRequest } from '@/lib/libraryRequest';
import type { LibraryVersion } from '@/types';

/**
 * The draft being written. It starts from the saved version, keeps every change
 * until it is saved, and sends the revision the page loaded, so a stale page is
 * refused by the server rather than overwriting someone else's draft. A failed
 * save keeps the text as it is.
 */
export function useDraftEditor(options: {
    version: () => LibraryVersion;
    templateId: () => number;
    savedText: string;
    failedText: string;
}) {
    const state = reactive<DraftState>(stateFromVersion(options.version()));
    const saved = ref(JSON.stringify(state));
    const saving = ref(false);
    const error = ref<string | null>(null);
    const notice = ref<string | null>(null);
    const dirty = computed(() => JSON.stringify(state) !== saved.value);

    /** When the server sends a newer version and nothing was typed meanwhile, the form takes it. */
    watch(
        () => options.version(),
        (version) => {
            if (JSON.stringify(state) === saved.value) {
                Object.assign(state, stateFromVersion(version));
                saved.value = JSON.stringify(state);
            }
        },
    );

    async function save(): Promise<void> {
        if (saving.value || !dirty.value) {
            return;
        }

        const current = JSON.stringify(state);
        const url = TemplateDraftController.save(options.templateId()).url;

        saving.value = true;
        error.value = null;
        notice.value = null;

        const result = await libraryRequest<unknown>(
            'PUT',
            url,
            draftPayload(state, options.version().draft_revision),
            options.failedText,
        );

        saving.value = false;

        if (!result.ok) {
            error.value = Object.values(result.errors)[0] ?? result.message;

            if (result.errors.draft_revision) {
                router.reload({ only: ['version', 'versions'] });
            }

            return;
        }

        saved.value = current;
        notice.value = options.savedText;
        router.reload({ only: ['version', 'versions'] });
    }

    return { state, dirty, saving, error, notice, save };
}
