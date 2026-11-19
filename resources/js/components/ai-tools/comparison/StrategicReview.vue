<script setup lang="ts">
import type { ComparisonSummary, StrategicItem } from '@/types';

defineProps<{ summary: ComparisonSummary }>();

function refs(item: StrategicItem): string {
    return item.pasal.join(', ');
}
</script>

<template>
    <section
        class="flex flex-col gap-6 rounded-xl border p-4 md:p-6"
        aria-labelledby="review-title"
    >
        <h2
            id="review-title"
            class="border-b-2 border-primary pb-2 text-lg font-bold tracking-wide uppercase"
        >
            Tinjauan strategis
        </h2>

        <div>
            <h3 class="mb-1 font-semibold text-primary">1. Pokok perubahan</h3>
            <p>{{ summary.pokok_perubahan }}</p>
        </div>

        <div v-if="summary.norma_baru_terpenting.length">
            <h3 class="mb-2 font-semibold text-primary">
                2. Norma baru terpenting
            </h3>
            <ul class="list-disc space-y-1.5 pl-5">
                <li
                    v-for="item in summary.norma_baru_terpenting"
                    :key="item.judul"
                >
                    <strong>{{ item.judul }}</strong>
                    <span v-if="item.pasal.length"> ({{ refs(item) }})</span>:
                    {{ item.uraian }}
                </li>
            </ul>
        </div>

        <div v-if="summary.dihapus_penting.length">
            <h3 class="mb-2 font-semibold text-primary">
                3. Ketentuan penting yang dihapus
            </h3>
            <ul class="list-disc space-y-1.5 pl-5">
                <li v-for="item in summary.dihapus_penting" :key="item.judul">
                    <strong>{{ item.judul }}</strong>
                    <span v-if="item.pasal.length"> ({{ refs(item) }})</span>:
                    {{ item.uraian }}
                </li>
            </ul>
        </div>

        <div v-if="summary.dampak_risiko.length">
            <h3 class="mb-2 font-semibold text-primary">
                4. Dampak dan risiko
            </h3>
            <ul class="list-disc space-y-1.5 pl-5">
                <li v-for="item in summary.dampak_risiko" :key="item">
                    {{ item }}
                </li>
            </ul>
        </div>

        <div v-if="summary.rekomendasi.length">
            <h3 class="mb-2 font-semibold text-primary">5. Rekomendasi</h3>
            <ul class="list-disc space-y-1.5 pl-5">
                <li v-for="item in summary.rekomendasi" :key="item">
                    {{ item }}
                </li>
            </ul>
        </div>

        <div
            v-if="summary.ketentuan_tetap.length"
            class="rounded-lg bg-muted/50 p-4"
        >
            <h3 class="mb-1 font-semibold">
                Ketentuan yang substansinya tetap ({{
                    summary.ketentuan_tetap.length
                }})
            </h3>
            <p class="text-sm text-muted-foreground">
                {{
                    summary.ketentuan_tetap
                        .map((item) => item.a ?? item.b ?? 'Pembukaan')
                        .join('; ')
                }}
            </p>
        </div>
    </section>
</template>
