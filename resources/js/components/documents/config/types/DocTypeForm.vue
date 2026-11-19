<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import DocTypeConfigController from '@/actions/App/Http/Controllers/Documents/Config/DocTypeConfigController';
import Panel from '@/components/documents/Panel.vue';
import BasicFields from '@/components/documents/config/types/BasicFields.vue';
import ClausePicker from '@/components/documents/config/types/ClausePicker.vue';
import SectionsEditor from '@/components/documents/config/types/SectionsEditor.vue';
import SignatureGroupsEditor from '@/components/documents/config/types/SignatureGroupsEditor.vue';
import FormActions from '@/components/documents/config/FormActions.vue';
import StringListEditor from '@/components/documents/config/StringListEditor.vue';
import HeaderLayoutEditor from '@/components/documents/config/header/HeaderLayoutEditor.vue';
import type {
    ChoiceOption,
    DocTypeForm,
    BrandColor,
    HeaderFieldOption,
    HeaderLayout,
    HeaderPalette,
    HeaderPreset,
} from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

const props = defineProps<{
    action: RouteFormDefinition<'post'>;
    submitLabel: string;
    type?: DocTypeForm;
    roleOptions: ChoiceOption[];
    clauseOptions: ChoiceOption[];
    headerFields: HeaderFieldOption[];
    headerDefault: HeaderLayout;
    headerPresets: HeaderPreset[];
    headerPalette: HeaderPalette;
    brandColors: BrandColor[];
    hasCompanyLogo: boolean;
}>();

const source = props.type;
const state = reactive({
    basic: {
        code: source?.code ?? '',
        name: source?.name ?? '',
        description: source?.description ?? '',
        layoutStyle: source?.layoutStyle ?? 'standard',
        headerStyle: source?.headerStyle ?? 'iso',
        bodyStyle: source?.bodyStyle ?? 'standard',
        letterhead: source?.letterhead ?? false,
        documentLevel: source?.documentLevel ?? 'Internal',
        processOwner: source?.processOwner ?? '',
    },
    sections: source?.defaultSections ?? [],
    groups: source?.signatureGroups ?? [
        { heading: '', columns: [{ label: 'Disetujui oleh', role: '' }] },
    ],
    header: JSON.parse(
        JSON.stringify(source?.headerLayout ?? props.headerDefault),
    ) as HeaderLayout,
    lanes: source?.defaultLanes ?? [],
    clauses: source?.defaultClauseIds ?? [],
});

useUnsavedGuard(() => state);

/** What the server expects: the form's pieces under the names of the API. */
function payload(): Record<string, never> {
    return {
        ...state.basic,
        defaultSections: state.sections,
        signatureGroups: state.groups.map((group) => ({
            heading: group.heading || null,
            columns: group.columns.map((column) => ({
                ...column,
                role: column.role || null,
                roleName: column.roleName || null,
            })),
        })),
        headerLayout: state.header,
        defaultLanes: state.lanes,
        defaultClauseIds: state.clauses,
    } as never;
}
</script>

<template>
    <Form
        v-bind="action"
        :transform="payload"
        class="flex flex-col gap-6"
        v-slot="{ errors, processing }"
    >
        <Panel title="Informasi dasar">
            <BasicFields
                v-model="state.basic"
                :errors="errors"
                :code-locked="!!type"
            />
        </Panel>
        <HeaderLayoutEditor
            v-model="state.header"
            :fields="headerFields"
            :default-layout="headerDefault"
            :presets="headerPresets"
            :palette="headerPalette"
            :brand-colors="brandColors"
            :has-company-logo="hasCompanyLogo"
        />
        <SectionsEditor v-model="state.sections" :errors="errors" />
        <SignatureGroupsEditor
            v-model="state.groups"
            :role-options="roleOptions"
            :errors="errors"
        />
        <Panel title="Lane diagram alir" :count="state.lanes.length">
            <p class="mb-3 text-sm text-muted-foreground">
                Pelaku yang tampil sebagai jalur (swimlane) saat penyusun
                membuat diagram alir. Kosong = memakai nama kolom tanda tangan.
            </p>
            <StringListEditor
                v-model="state.lanes"
                label="Nama lane"
                placeholder="Mis. Penyusun"
            />
        </Panel>
        <ClausePicker v-model="state.clauses" :options="clauseOptions" />
        <FormActions
            :cancel-href="DocTypeConfigController.index()"
            :processing="processing"
            :submit-label="submitLabel"
        />
    </Form>
</template>
