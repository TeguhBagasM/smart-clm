<script setup lang="ts">
import { Spinner } from '@/components/ui/spinner';
import type { ComparisonDetail } from '@/types';

defineProps<{ comparison: ComparisonDetail }>();
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border p-5"
        aria-live="polite"
    >
        <div class="flex items-center gap-3">
            <Spinner />
            <div>
                <p class="font-medium">
                    {{
                        comparison.stage?.label ??
                        'Menunggu giliran diproses...'
                    }}
                </p>
                <p class="text-sm text-muted-foreground">
                    Proses berjalan di latar belakang. Halaman ini diperbarui
                    otomatis; Anda boleh menutupnya dan kembali lewat riwayat.
                </p>
            </div>
        </div>
        <div
            class="h-2 overflow-hidden rounded-full bg-muted"
            role="progressbar"
            aria-label="Kemajuan perbandingan"
            :aria-valuenow="comparison.progress"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full rounded-full bg-primary transition-all"
                :style="{ width: `${Math.max(4, comparison.progress)}%` }"
            />
        </div>
        <ul class="text-sm text-muted-foreground">
            <li v-for="source in comparison.sources" :key="source.side">
                Dokumen {{ source.side.toUpperCase() }}:
                {{
                    source.pages ? `${source.pages} halaman` : 'menunggu dibaca'
                }}
            </li>
        </ul>
    </section>
</template>
