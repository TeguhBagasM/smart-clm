<script setup lang="ts">
import { Pencil } from '@lucide/vue';
import { ref } from 'vue';
import NumberingConfigController from '@/actions/App/Http/Controllers/Documents/Config/NumberingConfigController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import MaskInput from '@/components/documents/config/numbering/MaskInput.vue';
import { Button } from '@/components/ui/button';

/** Edits the company default mask, or the mask of one document type. */
const props = defineProps<{
    mask: string;
    tokens: string[];
    typeId?: string;
    typeCode?: string;
    suggested?: string;
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
        <div v-if="suggested" class="flex flex-col gap-1 text-sm">
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="self-start"
                @click="draft = suggested"
            >
                Pakai format yang disarankan
            </Button>
            <span class="text-xs text-muted-foreground">
                <code>{{ suggested }}</code> menghasilkan nomor seperti
                SOP-HED-27-00-26 (jenis, departemen, urutan, revisi, tahun).
                Urutan dimulai dari 1 tiap tahun untuk tiap jenis dokumen.
            </span>
        </div>
    </ActionDialog>
</template>
