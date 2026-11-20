<script setup lang="ts">
import { Pencil, Plus } from '@lucide/vue';
import { computed } from 'vue';
import DocumentTypeController from '@/actions/App/Http/Controllers/MasterData/DocumentTypeController';
import LookupRecordController from '@/actions/App/Http/Controllers/MasterData/LookupRecordController';
import PartnerController from '@/actions/App/Http/Controllers/MasterData/PartnerController';
import PartnerGroupController from '@/actions/App/Http/Controllers/MasterData/PartnerGroupController';
import DocumentTypeFields from '@/components/master-data/fields/DocumentTypeFields.vue';
import GroupFields from '@/components/master-data/fields/GroupFields.vue';
import LookupFields from '@/components/master-data/fields/LookupFields.vue';
import MasterFormSheet from '@/components/master-data/MasterFormSheet.vue';
import type { MasterFormOptions, MasterInfo, MasterRow } from '@/types';

/** The add/change sheet of one list: which route and which fields depend on the list. */
const props = defineProps<{
    info: MasterInfo;
    options: MasterFormOptions;
    row?: MasterRow;
    companyId: number | null;
}>();

const form = computed(() => {
    const controller = {
        'document-types': DocumentTypeController,
        'partner-groups': PartnerGroupController,
        partners: PartnerController,
    }[props.info.key];

    if (controller) {
        return props.row
            ? controller.update.form(props.row.id)
            : controller.store.form();
    }

    return props.row
        ? LookupRecordController.update.form({
              list: props.info.key,
              record: props.row.id,
          })
        : LookupRecordController.store.form(props.info.key);
});

const fields = computed(() =>
    props.info.key === 'document-types'
        ? DocumentTypeFields
        : ['partner-groups', 'partners'].includes(props.info.key)
          ? GroupFields
          : LookupFields,
);
</script>

<template>
    <MasterFormSheet
        :form="form"
        :title="row ? `Ubah ${row.name}` : `Tambah ${info.label.toLowerCase()}`"
        :description="info.description"
        :trigger-label="row ? 'Ubah' : `Tambah ${info.label.toLowerCase()}`"
        :trigger-icon="row ? Pencil : Plus"
        :trigger-variant="row ? 'ghost' : 'default'"
        :submit-label="row ? 'Simpan' : 'Tambah'"
        :fail-text="info.texts.save_failed"
        :company-id="companyId"
        v-slot="{ errors }"
    >
        <component
            :is="fields"
            :info="info"
            :row="row"
            :options="options"
            :errors="errors"
        />
    </MasterFormSheet>
</template>
