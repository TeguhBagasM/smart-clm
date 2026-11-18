<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import { ref } from 'vue';
import DocumentCreateController from '@/actions/App/Http/Controllers/Documents/DocumentCreateController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineProps<{ errors: Record<string, string> }>();

const emit = defineEmits<{
    suggested: [
        data: {
            title?: string;
            docTypeCode?: string;
            department?: string | null;
        },
    ];
}>();

const input = ref<HTMLInputElement | null>(null);
const reading = ref(false);
const message = ref('');

function csrf(): string {
    return decodeURIComponent(
        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );
}

/** Asks the AI to read the chosen PDF and propose title, type and department. */
async function suggest(): Promise<void> {
    const file = input.value?.files?.[0];

    if (!file) {
        message.value = 'Pilih berkas PDF terlebih dahulu.';
        return;
    }

    reading.value = true;
    message.value = '';
    const body = new FormData();
    body.append('file', file);

    try {
        const answer = await fetch(
            DocumentCreateController.extractMetadata().url,
            {
                method: 'POST',
                body,
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
                credentials: 'same-origin',
            },
        );
        const data = await answer.json();

        if (!answer.ok) throw new Error(data.message ?? data.error);
        emit('suggested', data);
        message.value = 'Saran terisi. Periksa kembali sebelum menyimpan.';
    } catch (error) {
        message.value =
            error instanceof Error ? error.message : 'Gagal membaca dokumen.';
    } finally {
        reading.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-4 rounded-lg border p-4">
        <div class="flex flex-col gap-1.5">
            <Label for="doc-file">Berkas PDF</Label>
            <input
                id="doc-file"
                ref="input"
                type="file"
                name="file"
                accept="application/pdf"
                class="text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm file:font-medium"
            />
            <p class="text-xs text-muted-foreground">PDF, maksimal 10 MB.</p>
            <InputError :message="errors.file" />
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="reading"
                @click="suggest"
            >
                <Spinner v-if="reading" />
                <Sparkles v-else /> Isi otomatis dengan AI
            </Button>
            <span v-if="message" class="text-sm text-muted-foreground">
                {{ message }}
            </span>
        </div>
        <div class="flex flex-col gap-1.5">
            <Label for="manual-number">Nomor dokumen (opsional)</Label>
            <Input
                id="manual-number"
                name="manualNumber"
                maxlength="80"
                placeholder="Kosong = nomor otomatis; isi bila dokumen sudah bernomor"
            />
        </div>
    </div>
</template>
