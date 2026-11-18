<script setup lang="ts">
import { MessageSquareWarning } from '@lucide/vue';
import VersionWorkflowController from '@/actions/App/Http/Controllers/Documents/VersionWorkflowController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import AnchorFields from '@/components/documents/actions/AnchorFields.vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

defineProps<{ documentId: string; versionId: string }>();
</script>

<template>
    <ActionDialog
        :form="
            VersionWorkflowController.requestChanges.form({
                document: documentId,
                version: versionId,
            })
        "
        trigger-label="Minta revisi"
        :trigger-icon="MessageSquareWarning"
        title="Minta revisi"
        description="Dokumen kembali ke penyusun dan semua tanda tangan direset. Jelaskan apa yang perlu diperbaiki."
        submit-variant="destructive"
        v-slot="{ errors }"
    >
        <div class="flex flex-col gap-1.5">
            <Label for="change-note">Catatan revisi</Label>
            <Textarea
                id="change-note"
                name="body"
                rows="4"
                placeholder="Mis. Bab 2 belum sesuai prosedur terbaru."
            />
            <InputError :message="errors.body" />
        </div>
        <AnchorFields />
    </ActionDialog>
</template>
