<script setup lang="ts">
import { Power, RotateCcw } from '@lucide/vue';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import type { RouteFormDefinition } from '@/wayfinder';

defineProps<{
    active: boolean;
    name: string;
    deactivate: RouteFormDefinition<'post'>;
    restore: RouteFormDefinition<'post'>;
}>();
</script>

<template>
    <ActionDialog
        v-if="active"
        :form="deactivate"
        trigger-label="Nonaktifkan"
        :trigger-icon="Power"
        trigger-variant="ghost"
        :title="`Nonaktifkan ${name}?`"
        description="Tidak dihapus: dokumen yang sudah ada tidak berubah, hanya tidak bisa dipilih untuk dokumen baru. Bisa diaktifkan kembali kapan saja."
        submit-variant="destructive"
    />
    <ActionDialog
        v-else
        :form="restore"
        trigger-label="Aktifkan kembali"
        :trigger-icon="RotateCcw"
        trigger-variant="ghost"
        :title="`Aktifkan kembali ${name}?`"
        description="Bisa dipilih lagi untuk dokumen baru."
    />
</template>
