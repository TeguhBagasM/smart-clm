<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { Lock } from '@lucide/vue';
import { ref, watch } from 'vue';
import DocumentCreateController from '@/actions/App/Http/Controllers/Documents/DocumentCreateController';

const props = defineProps<{ typeCode: string; department: string }>();

const number = ref<string | null>(null);

const load = useDebounceFn(async () => {
    if (!props.typeCode || !props.department.trim()) {
        number.value = null;
        return;
    }

    const { url } = DocumentCreateController.numberPreview({
        query: {
            docTypeCode: props.typeCode,
            department: props.department.trim(),
        },
    });
    const answer = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });
    number.value = ((await answer.json()) as { number: string | null }).number;
}, 300);

watch(
    () => [props.typeCode, props.department],
    () => void load(),
    {
        immediate: true,
    },
);
</script>

<template>
    <div
        class="flex items-center gap-2 rounded-md border bg-muted px-3 py-2 text-sm"
        aria-live="polite"
    >
        <Lock class="size-4 shrink-0 text-muted-foreground" />
        <span v-if="number" class="font-mono font-medium">{{ number }}</span>
        <span v-else class="text-muted-foreground italic">
            Pilih jenis dokumen dan departemen untuk melihat nomornya
        </span>
    </div>
    <p v-if="number" class="text-xs text-muted-foreground">
        Nomor diberikan otomatis saat dokumen disimpan. Ganti departemen berarti
        urutan nomor ikut berganti.
    </p>
</template>
