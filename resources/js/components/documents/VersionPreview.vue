<script setup lang="ts">
import { Download, ExternalLink, FileX } from '@lucide/vue';
import { ref } from 'vue';
import VersionFileController from '@/actions/App/Http/Controllers/Documents/VersionFileController';
import { Button } from '@/components/ui/button';
import type { VersionDetail } from '@/types';

const props = defineProps<{ documentId: string; version: VersionDetail }>();

const english = ref(false);

function url(download = false): string {
    return VersionFileController.show(
        { document: props.documentId, version: props.version.id },
        {
            query: {
                ...(english.value ? { lang: 'en' } : {}),
                ...(download ? { download: '1' } : {}),
            },
        },
    ).url;
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div v-if="version.has_translation" class="flex gap-1">
                <Button
                    size="sm"
                    :variant="english ? 'outline' : 'default'"
                    @click="english = false"
                >
                    Bahasa Indonesia
                </Button>
                <Button
                    size="sm"
                    :variant="english ? 'default' : 'outline'"
                    @click="english = true"
                >
                    English
                </Button>
            </div>
            <span v-else />
            <div v-if="version.has_file" class="flex gap-2">
                <Button variant="outline" size="sm" as-child>
                    <a :href="url()" target="_blank" rel="noopener">
                        <ExternalLink /> Buka di tab baru
                    </a>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <a :href="url(true)"><Download /> Unduh</a>
                </Button>
            </div>
        </div>
        <iframe
            v-if="version.has_file"
            :key="url()"
            :src="url()"
            title="Pratinjau dokumen"
            class="h-[75vh] w-full rounded-xl border bg-muted"
        />
        <div
            v-else
            class="flex h-64 flex-col items-center justify-center gap-2 rounded-xl border border-dashed text-muted-foreground"
        >
            <FileX class="size-8" />
            Versi ini belum memiliki berkas PDF.
        </div>
        <p v-if="version.has_file" class="text-xs text-muted-foreground">
            Salinan ini diberi watermark sesuai status dokumen saat dibuka, dan
            pembukaannya tercatat sebagai tanda terima baca.
        </p>
    </div>
</template>
