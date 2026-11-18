<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import DocumentDraftController from '@/actions/App/Http/Controllers/Documents/DocumentDraftController';
import { steps } from '@/components/documents/wizard/steps';
import type { StepKey } from '@/components/documents/wizard/steps';
import AttachmentsStep from '@/components/documents/wizard/steps/AttachmentsStep.vue';
import FlowStep from '@/components/documents/wizard/steps/FlowStep.vue';
import PurposeScopeStep from '@/components/documents/wizard/steps/PurposeScopeStep.vue';
import ReviewStep from '@/components/documents/wizard/steps/ReviewStep.vue';
import SectionsStep from '@/components/documents/wizard/steps/SectionsStep.vue';
import SignersStep from '@/components/documents/wizard/steps/SignersStep.vue';
import WizardShell from '@/components/documents/wizard/WizardShell.vue';
import {
    provideDraftWizard,
    useDraftWizard,
} from '@/composables/useDraftWizard';
import type {
    ChoiceOption,
    DocumentDetail,
    Draft,
    WizardVersion,
} from '@/types';

defineOptions({ layout: { breadcrumbs: [] } });

const props = defineProps<{
    document: DocumentDetail;
    version: WizardVersion;
    draft: Draft;
    user_options: ChoiceOption[];
    role_options: ChoiceOption[];
    clause_options: ChoiceOption[];
}>();

const params = { document: props.document.id, version: props.version.id };
const wizard = useDraftWizard(
    props.draft,
    DocumentDraftController.update.url(params),
);

provideDraftWizard({
    ...wizard,
    options: {
        users: props.user_options,
        roles: props.role_options,
        clauses: props.clause_options,
    },
    documentId: props.document.id,
    versionId: props.version.id,
});

function fromHash(): StepKey {
    const key = window.location.hash.slice(1);

    return steps.find((step) => step.key === key)?.key ?? 'purpose';
}

const step = ref<StepKey>(fromHash());

function go(next: StepKey): void {
    step.value = next;
    history.replaceState(null, '', `#${next}`);
    window.scrollTo({ top: 0 });
    void wizard.flush();
}

const warn = (event: BeforeUnloadEvent): void => {
    if (wizard.status.value !== 'saved') event.preventDefault();
};

window.addEventListener('beforeunload', warn);
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', warn);
    void wizard.flush();
});
</script>

<template>
    <Head :title="`Susun ${document.title}`" />

    <WizardShell
        :document="document"
        :version="version"
        :draft="draft"
        :status="wizard.status.value"
        :step="step"
        @go="go"
    >
        <PurposeScopeStep v-if="step === 'purpose'" />
        <SectionsStep v-else-if="step === 'body'" />
        <FlowStep v-else-if="step === 'flow'" />
        <AttachmentsStep v-else-if="step === 'attachments'" />
        <SignersStep v-else-if="step === 'signers'" />
        <ReviewStep v-else :draft="draft" />
    </WizardShell>
</template>
