<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import NumberingConfigController from '@/actions/App/Http/Controllers/Documents/Config/NumberingConfigController';
import type { NumberFormat } from '@/types';

/** The pattern this type's documents are numbered with, and an example of the first number. */
defineProps<{ format: NumberFormat }>();
</script>

<template>
    <div
        class="flex flex-col gap-1 rounded-lg border bg-muted/40 p-3 text-sm"
        aria-label="Format nomor dokumen"
    >
        <span class="font-medium">Format nomor</span>
        <span>
            <code>{{ format.mask }}</code>
            <span class="text-muted-foreground"> · Contoh: </span>
            <span class="font-mono">{{ format.example }}</span>
        </span>
        <span class="text-xs text-muted-foreground">
            {{
                format.custom
                    ? 'Format khusus jenis ini.'
                    : 'Memakai format default perusahaan.'
            }}
            Diatur di
            <Link
                :href="NumberingConfigController.index()"
                class="text-primary underline underline-offset-4"
                >Penomoran</Link
            >; nomor yang sudah terbit tidak berubah.
        </span>
    </div>
</template>
