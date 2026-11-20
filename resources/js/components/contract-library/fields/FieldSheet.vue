<script setup lang="ts">
import { Pencil, Plus } from '@lucide/vue';
import FieldController from '@/actions/App/Http/Controllers/Templates/VariableController';
import FieldFields from '@/components/contract-library/fields/FieldFields.vue';
import MasterFormSheet from '@/components/master-data/MasterFormSheet.vue';
import type { LibraryFieldRow, LibraryPageInfo } from '@/types';

/** Add or change a field: a sheet, full screen on a phone. */
defineProps<{ info: LibraryPageInfo; row?: LibraryFieldRow }>();
</script>

<template>
    <MasterFormSheet
        :form="
            row
                ? FieldController.update.form(row.id)
                : FieldController.store.form()
        "
        :title="row ? `Ubah ${row.name}` : info.add"
        :description="info.description"
        :trigger-label="row ? 'Ubah' : info.add"
        :trigger-icon="row ? Pencil : Plus"
        :trigger-variant="row ? 'ghost' : 'default'"
        :submit-label="row ? 'Simpan' : 'Tambah'"
        :fail-text="info.texts.save_failed"
        v-slot="{ errors }"
    >
        <FieldFields :row="row" :info="info" :errors="errors" />
    </MasterFormSheet>
</template>
