<script setup lang="ts">
import { Pencil, Plus } from '@lucide/vue';
import VendorController from '@/actions/App/Http/Controllers/MasterData/VendorController';
import VendorFields from '@/components/master-data/fields/VendorFields.vue';
import MasterFormSheet from '@/components/master-data/MasterFormSheet.vue';
import type { VendorInfo, VendorRow } from '@/types';

defineProps<{
    info: VendorInfo;
    row?: VendorRow;
    companyId: number | null;
}>();
</script>

<template>
    <MasterFormSheet
        :form="
            row
                ? VendorController.update.form(row.id)
                : VendorController.store.form()
        "
        :title="row ? `Ubah ${row.name}` : info.add"
        :description="info.description"
        :trigger-label="row ? 'Ubah' : info.add"
        :trigger-icon="row ? Pencil : Plus"
        :trigger-variant="row ? 'ghost' : 'default'"
        :submit-label="row ? 'Simpan' : 'Tambah'"
        :fail-text="info.texts.save_failed"
        :company-id="companyId"
        v-slot="{ errors }"
    >
        <VendorFields :info="info" :row="row" :errors="errors" />
    </MasterFormSheet>
</template>
