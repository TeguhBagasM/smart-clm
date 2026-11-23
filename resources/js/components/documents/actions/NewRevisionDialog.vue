<script setup lang="ts">
import type { Component } from 'vue';
import { ref } from 'vue';
import DocumentRevisionController from '@/actions/App/Http/Controllers/Documents/DocumentRevisionController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

defineProps<{ documentId: string; triggerIcon?: Component }>();

const bump = ref<'revisi' | 'terbitan'>('revisi');

const choices = [
    {
        value: 'revisi' as const,
        label: 'Revisi',
        help: 'Perubahan kecil. Terbitan sama, nomor revisi naik satu.',
    },
    {
        value: 'terbitan' as const,
        label: 'Terbitan baru',
        help: 'Perubahan besar. Nomor terbitan naik satu, revisi kembali ke 0.',
    },
];
</script>

<template>
    <ActionDialog
        :form="DocumentRevisionController.store.form({ document: documentId })"
        trigger-label="Buat revisi baru"
        :trigger-icon="triggerIcon"
        trigger-variant="default"
        title="Buat revisi baru"
        description="Draf baru dibuat dari versi terakhir. Versi yang berlaku tetap berlaku sampai draf baru diberlakukan."
        submit-label="Buat draf"
        v-slot="{ errors }"
    >
        <input type="hidden" name="bump" :value="bump" />
        <div class="grid gap-2 sm:grid-cols-2">
            <Button
                v-for="choice in choices"
                :key="choice.value"
                type="button"
                class="h-auto min-h-11 flex-col items-start gap-0.5 py-2 text-left whitespace-normal"
                :variant="bump === choice.value ? 'default' : 'outline'"
                :aria-pressed="bump === choice.value"
                @click="bump = choice.value"
            >
                <span>{{ choice.label }}</span>
                <span class="text-xs font-normal opacity-85">{{
                    choice.help
                }}</span>
            </Button>
        </div>
        <InputError :message="errors.bump" />
        <div class="flex flex-col gap-1.5">
            <Label for="change-summary"
                >Ringkasan perubahan (boleh dikosongkan)</Label
            >
            <Textarea
                id="change-summary"
                name="change_summary"
                rows="3"
                maxlength="1000"
                placeholder="Contoh: menambah langkah verifikasi pada bagian 3."
            />
            <InputError :message="errors.change_summary" />
        </div>
    </ActionDialog>
</template>
