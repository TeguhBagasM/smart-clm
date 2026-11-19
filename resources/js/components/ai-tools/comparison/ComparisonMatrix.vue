<script setup lang="ts">
import { computed, ref } from 'vue';
import MatrixCard from '@/components/ai-tools/comparison/MatrixCard.vue';
import MatrixRow from '@/components/ai-tools/comparison/MatrixRow.vue';
import { Button } from '@/components/ui/button';
import type { ChangeKindValue, ComparisonDetail } from '@/types';

const props = defineProps<{ comparison: ComparisonDetail }>();

const filter = ref<ChangeKindValue | 'all'>('all');

const counts = computed(() => props.comparison.summary?.counts);
const filters: [ChangeKindValue | 'all', string][] = [
    ['all', 'Semua'],
    ['changed', 'Diubah'],
    ['added', 'Norma Baru'],
    ['removed', 'Dihapus Total'],
    ['unchanged', 'Tetap'],
];
const rows = computed(() =>
    filter.value === 'all'
        ? props.comparison.rows
        : props.comparison.rows.filter(
              (row) => row.kind.value === filter.value,
          ),
);

function count(key: ChangeKindValue | 'all'): number {
    return key === 'all'
        ? props.comparison.rows.length
        : (counts.value?.[key] ?? 0);
}
</script>

<template>
    <section class="flex flex-col gap-4" aria-labelledby="matrix-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="matrix-title" class="text-lg font-semibold">
                Matriks sandingan
            </h2>
            <div class="flex flex-wrap gap-2" role="group" aria-label="Saring">
                <Button
                    v-for="[key, text] in filters"
                    :key="key"
                    type="button"
                    size="sm"
                    :variant="filter === key ? 'default' : 'outline'"
                    :aria-pressed="filter === key"
                    @click="filter = key"
                >
                    {{ text }} ({{ count(key) }})
                </Button>
            </div>
        </div>

        <p v-if="rows.length === 0" class="text-sm text-muted-foreground">
            Tidak ada baris untuk saringan ini.
        </p>

        <ul class="flex flex-col gap-3 lg:hidden">
            <MatrixCard
                v-for="row in rows"
                :key="row.no"
                :row="row"
                :label-a="comparison.label_a"
                :label-b="comparison.label_b"
            />
        </ul>

        <div
            v-if="rows.length"
            class="hidden overflow-x-auto rounded-xl border lg:block"
        >
            <table class="w-full min-w-[60rem] border-collapse text-left">
                <thead class="bg-muted/60 text-sm">
                    <tr>
                        <th scope="col" class="p-3 font-medium">Pasal A</th>
                        <th scope="col" class="p-3 font-medium">
                            Isi {{ comparison.label_a }}
                        </th>
                        <th scope="col" class="p-3 font-medium">Pasal B</th>
                        <th scope="col" class="p-3 font-medium">
                            Isi {{ comparison.label_b }}
                        </th>
                        <th scope="col" class="p-3 font-medium">
                            Analisis perubahan
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <MatrixRow v-for="row in rows" :key="row.no" :row="row" />
                </tbody>
            </table>
        </div>
    </section>
</template>
