<script setup lang="ts">
import { CheckCircle2 } from '@lucide/vue';
import ExternalReviewPageController from '@/actions/App/Http/Controllers/Documents/ExternalReviewPageController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

defineProps<{ token: string; name: string }>();
</script>

<template>
    <ActionDialog
        :form="ExternalReviewPageController.approve.form({ token })"
        trigger-label="Setujui dokumen ini"
        :trigger-icon="CheckCircle2"
        trigger-variant="default"
        title="Setujui dokumen ini?"
        description="Persetujuan Anda dicatat dengan nama di bawah, lalu tinjauan dikunci. Setelah itu Anda tidak bisa menambah komentar kecuali pemilik dokumen membukanya kembali."
        submit-label="Ya, saya setuju"
        v-slot="{ errors }"
    >
        <input type="hidden" name="name" :value="name" />
        <p class="text-sm">
            Atas nama:
            <strong>{{
                name || '(isi nama Anda di formulir komentar)'
            }}</strong>
        </p>
        <InputError :message="errors.name" />
        <div class="flex flex-col gap-1.5">
            <Label for="approve-note">Catatan (boleh dikosongkan)</Label>
            <Textarea id="approve-note" name="note" rows="3" maxlength="300" />
            <InputError :message="errors.note" />
        </div>
    </ActionDialog>
</template>
