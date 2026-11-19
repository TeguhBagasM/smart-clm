<script setup lang="ts">
import { ImageUp, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import HeaderAssetController from '@/actions/App/Http/Controllers/Documents/Config/HeaderAssetController';
import { Button } from '@/components/ui/button';
import { uploadFile } from '@/lib/http';
import { cn } from '@/lib/utils';

const assetId = defineModel<string | null>({ required: true });

const MAX_BYTES = 2 * 1024 * 1024;
const TYPES = ['image/png', 'image/jpeg', 'image/webp'];

const input = ref<HTMLInputElement | null>(null);
const progress = ref<number | null>(null);
const error = ref('');
const dragging = ref(false);

async function upload(file: File | undefined): Promise<void> {
    if (!file) return;

    error.value = '';

    if (!TYPES.includes(file.type)) {
        error.value = 'Logo harus berformat PNG, JPG atau WebP.';

        return;
    }

    if (file.size > MAX_BYTES) {
        error.value = 'Ukuran logo maksimal 2 MB.';

        return;
    }

    progress.value = 0;

    try {
        const asset = await uploadFile<{ id: string }>(
            HeaderAssetController.store.url(),
            file,
            (percent) => (progress.value = percent),
        );
        assetId.value = asset.id;
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Unggahan gagal.';
    } finally {
        progress.value = null;
    }
}

function drop(event: DragEvent): void {
    dragging.value = false;
    void upload(event.dataTransfer?.files[0]);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            v-if="assetId"
            class="flex items-center gap-3 rounded-md border p-2"
        >
            <img
                :src="HeaderAssetController.show.url(assetId)"
                alt="Logo khusus jenis dokumen ini"
                class="h-14 max-w-32 object-contain"
            />
            <div class="ml-auto flex gap-1">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="input?.click()"
                >
                    <ImageUp /> Ganti
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="assetId = null"
                >
                    <Trash2 class="text-destructive" /> Hapus
                </Button>
            </div>
        </div>
        <button
            v-else
            type="button"
            :class="
                cn(
                    'flex flex-col items-center gap-1 rounded-md border-2 border-dashed p-4 text-sm text-muted-foreground hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/70 focus-visible:outline-hidden',
                    dragging && 'border-primary bg-accent',
                )
            "
            @click="input?.click()"
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="drop"
        >
            <ImageUp class="size-6" />
            Tarik gambar ke sini atau klik untuk memilih
            <span class="text-xs">PNG, JPG atau WebP, maksimal 2 MB</span>
        </button>
        <input
            ref="input"
            type="file"
            accept="image/png,image/jpeg,image/webp"
            class="sr-only"
            tabindex="-1"
            aria-label="Berkas logo"
            @change="upload(($event.target as HTMLInputElement).files?.[0])"
        />
        <div
            v-if="progress !== null"
            class="h-2 overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="progress"
        >
            <div
                class="h-full bg-primary transition-all"
                :style="{ width: `${progress}%` }"
            />
        </div>
        <p v-if="error" class="text-sm text-destructive" role="alert">
            {{ error }}
        </p>
    </div>
</template>
