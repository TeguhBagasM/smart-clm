<script setup lang="ts">
import { computed } from 'vue';
import ClausePicker from '@/components/contract-library/editor/ClausePicker.vue';
import DraftClauses from '@/components/contract-library/editor/DraftClauses.vue';
import DraftFields from '@/components/contract-library/editor/DraftFields.vue';
import DraftParagraphs from '@/components/contract-library/editor/DraftParagraphs.vue';
import DraftParties from '@/components/contract-library/editor/DraftParties.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useDraftEditor } from '@/composables/useDraftEditor';
import { missingFields } from '@/lib/contractLibrary';
import type {
    LibraryClauseOption,
    LibraryEditorTexts,
    LibraryFieldOption,
    LibraryPageInfo,
    LibraryVersion,
} from '@/types';

/**
 * The draft editor: paragraphs, parties, clauses in order, and fields, with the
 * library beside them. One save button sends everything with the revision the
 * page loaded.
 */
const props = defineProps<{
    version: LibraryVersion;
    templateId: number;
    clauses: LibraryClauseOption[];
    fields: LibraryFieldOption[];
    info: LibraryPageInfo;
    texts: LibraryEditorTexts;
}>();

const { state, dirty, saving, error, notice, save } = useDraftEditor({
    version: () => props.version,
    templateId: () => props.templateId,
    savedText: props.texts.saved,
    failedText: props.texts.save_failed,
});

const fieldCodes = computed(
    () => new Map(props.fields.map((field) => [field.value, field.code])),
);
const missing = computed(() =>
    missingFields(state, fieldCodes.value, props.info.system_tokens),
);
const usedIds = computed(() => state.clauses.map((clause) => clause.libraryId));
const knownFields = computed(() => props.fields.map((field) => field.code));

function addClause(option: LibraryClauseOption): void {
    state.clauses.push({
        libraryId: option.value,
        title: option.label,
        body: option.body,
        sourceUpdated: false,
    });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="flex min-w-0 flex-col gap-8">
                <DraftParagraphs
                    v-model:opening="state.opening"
                    v-model:closing="state.closing"
                    v-model:addendum="state.addendum"
                    :labels="texts.labels"
                    :hints="info.hints"
                    :known-fields="knownFields"
                    :system-tokens="info.system_tokens"
                />
                <DraftParties v-model="state.parties" :labels="texts.labels" />
                <DraftClauses v-model="state.clauses" :labels="texts.labels" />
                <p v-if="state.clauses.length === 0" class="sr-only">
                    {{ texts.no_clauses }}
                </p>
                <DraftFields
                    v-model="state.fields"
                    :options="fields"
                    :labels="texts.labels"
                    :missing="missing"
                />
            </div>
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <ClausePicker
                    :options="clauses"
                    :used-ids="usedIds"
                    :labels="texts.labels"
                    @add="addClause"
                />
            </aside>
        </div>
        <div
            class="sticky bottom-0 -mx-4 flex flex-col gap-2 border-t bg-background/95 p-4 backdrop-blur md:-mx-6 md:px-6"
        >
            <Alert v-if="error" variant="destructive" class="text-sm"
                ><AlertDescription>{{ error }}</AlertDescription></Alert
            >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted-foreground" aria-live="polite">
                    {{ dirty ? texts.unsaved : texts.saved_now }}
                </p>
                <Button
                    type="button"
                    class="h-11 md:h-9"
                    :disabled="!dirty || saving"
                    @click="save"
                >
                    {{ saving ? '…' : texts.save }}
                </Button>
            </div>
        </div>
    </div>
</template>
