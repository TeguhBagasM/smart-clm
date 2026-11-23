<script setup lang="ts">
import { FilePlus2, Undo2 } from '@lucide/vue';
import DocumentNumberController from '@/actions/App/Http/Controllers/Documents/DocumentNumberController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import NewRevisionDialog from '@/components/documents/actions/NewRevisionDialog.vue';
import type { DocumentDetail } from '@/types';

defineProps<{ document: DocumentDetail }>();
</script>

<template>
    <NewRevisionDialog
        v-if="document.can.create_version"
        :document-id="document.id"
        :trigger-icon="FilePlus2"
    />
    <ActionDialog
        v-if="document.can.release_number"
        :form="DocumentNumberController.release.form({ document: document.id })"
        trigger-label="Lepas nomor"
        :trigger-icon="Undo2"
        trigger-variant="outline"
        submit-variant="destructive"
        title="Lepas nomor dokumen ini?"
        :description="`Nomor ${document.number} dikembalikan dan dokumen ini tetap tersimpan dengan nomor sementara. Hanya untuk dokumen yang dibatalkan dan belum pernah ditandatangani. Tindakan ini tidak bisa dibatalkan.`"
    />
</template>
