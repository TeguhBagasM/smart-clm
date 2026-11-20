<script setup lang="ts">
import { Pencil, Plus } from '@lucide/vue';
import EmployeeController from '@/actions/App/Http/Controllers/MasterData/EmployeeController';
import EmployeeFields from '@/components/master-data/fields/EmployeeFields.vue';
import MasterFormSheet from '@/components/master-data/MasterFormSheet.vue';
import type { EmployeeFormOptions, EmployeeRow, MasterInfo } from '@/types';

defineProps<{
    info: MasterInfo;
    options: EmployeeFormOptions;
    row?: EmployeeRow;
    companyId: number | null;
}>();
</script>

<template>
    <MasterFormSheet
        :form="
            row
                ? EmployeeController.update.form(row.id)
                : EmployeeController.store.form()
        "
        :title="row ? `Ubah ${row.name}` : 'Tambah karyawan'"
        :description="info.description"
        :trigger-label="row ? 'Ubah' : 'Tambah karyawan'"
        :trigger-icon="row ? Pencil : Plus"
        :trigger-variant="row ? 'ghost' : 'default'"
        :submit-label="row ? 'Simpan' : 'Tambah'"
        :fail-text="info.texts.save_failed"
        :company-id="companyId"
        v-slot="{ errors }"
    >
        <EmployeeFields
            :info="info"
            :row="row"
            :options="options"
            :errors="errors"
        />
    </MasterFormSheet>
</template>
