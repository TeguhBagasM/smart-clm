<script setup lang="ts">
import { Paperclip, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AppendixUploadController from '@/actions/App/Http/Controllers/Documents/DocumentCreateController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { postForm } from '@/lib/http';
import type { DraftAppendix } from '@/types';

const model = defineModel<DraftAppendix>({ required: true });

defineProps<{ index: number }>();
defineEmits<{ remove: [] }>();

const busy = ref(false);
const error = ref('');

async function upload(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) return;

    busy.value = true;
    error.value = '';

    try {
        const body = new FormData();
        body.append('file', file);
        const saved = await postForm<{ url: string; name: string }>(
            AppendixUploadController.appendixUpload.url(),
            body,
        );
        model.value = {
            label: model.value.label || saved.name,
            url: saved.url,
        };
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Gagal mengunggah.';
    } finally {
        busy.value = false;
        input.value = '';
    }
}
</script>

<template>
    <li class="flex flex-col gap-2 rounded-lg border p-3">
        <div class="flex items-center gap-1">
            <Input
                :model-value="model.label"
                placeholder="Nama lampiran"
                :aria-label="`Nama lampiran ${index + 1}`"
                @update:model-value="
                    model = { ...model, label: String($event) }
                "
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Hapus lampiran"
                @click="$emit('remove')"
            >
                <Trash2 class="text-destructive" />
            </Button>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <Input
                type="file"
                class="max-w-xs"
                :disabled="busy"
                :aria-label="`Berkas lampiran ${index + 1}`"
                @change="upload"
            />
            <a
                v-if="model.url"
                :href="model.url"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1 text-primary hover:underline"
            >
                <Paperclip class="size-4" /> Lihat berkas
            </a>
            <span v-if="busy" class="text-muted-foreground">Mengunggah…</span>
        </div>
        <p v-if="error" role="alert" class="text-sm text-destructive">
            {{ error }}
        </p>
    </li>
</template>
