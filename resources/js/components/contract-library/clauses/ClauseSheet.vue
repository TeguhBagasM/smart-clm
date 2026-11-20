<script setup lang="ts">
import { Pencil, Plus } from '@lucide/vue';
import ClauseController from '@/actions/App/Http/Controllers/Templates/ClauseController';
import ClauseFields from '@/components/contract-library/clauses/ClauseFields.vue';
import MasterFormSheet from '@/components/master-data/MasterFormSheet.vue';
import type { LibraryClauseRow, LibraryPageInfo } from '@/types';

/** Add or change a clause: a sheet, full screen on a phone. */
defineProps<{
    info: LibraryPageInfo;
    categories: { value: string; label: string }[];
    fieldCodes: string[];
    row?: LibraryClauseRow;
}>();
</script>

<template>
    <MasterFormSheet
        :form="
            row
                ? ClauseController.update.form(row.id)
                : ClauseController.store.form()
        "
        :title="row ? `Ubah ${row.title}` : info.add"
        :description="info.description"
        :trigger-label="row ? 'Ubah' : info.add"
        :trigger-icon="row ? Pencil : Plus"
        :trigger-variant="row ? 'ghost' : 'default'"
        :submit-label="row ? 'Simpan' : 'Tambah'"
        :fail-text="info.texts.save_failed"
        v-slot="{ errors }"
    >
        <ClauseFields
            :row="row"
            :info="info"
            :categories="categories"
            :field-codes="fieldCodes"
            :errors="errors"
        />
    </MasterFormSheet>
</template>
