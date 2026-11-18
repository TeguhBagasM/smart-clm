<script setup lang="ts">
import { Pencil } from '@lucide/vue';
import { ref } from 'vue';
import NumberingConfigController from '@/actions/App/Http/Controllers/Documents/Config/NumberingConfigController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import MaskInput from '@/components/documents/config/numbering/MaskInput.vue';

/** Edits the company default mask, or the mask of one document type. */
const props = defineProps<{
    mask: string;
    tokens: string[];
    typeId?: string;
    typeCode?: string;
}>();

const draft = ref(props.mask);
</script>

<template>
    <ActionDialog
        :form="NumberingConfigController.update.form()"
        :trigger-icon="Pencil"
        trigger-label="Ubah format"
        :title="
            typeCode
                ? `Format nomor ${typeCode}`
                : 'Format nomor default perusahaan'
        "
        description="Berlaku untuk nomor yang diberikan mulai sekarang. Nomor yang sudah terbit tidak berubah, dan urutan tiap jenis tetap berlanjut."
        submit-label="Simpan format"
        v-slot="{ errors }"
        @opened="draft = mask"
    >
        <input v-if="typeId" type="hidden" name="docTypeId" :value="typeId" />
        <MaskInput
            v-model="draft"
            :tokens="tokens"
            :type-id="typeId"
            :error="errors.mask"
        />
    </ActionDialog>
</template>
