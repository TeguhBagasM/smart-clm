<script setup lang="ts">
import type { DiffRun } from '@/types';

defineProps<{ runs: DiffRun[] }>();
</script>

<template>
    <p class="leading-relaxed whitespace-pre-line">
        <template v-for="(run, index) in runs" :key="index">
            <span
                v-if="run.o === 'del'"
                class="rounded-sm bg-destructive/10 px-0.5 line-through decoration-destructive decoration-2"
            >
                <span class="sr-only">(dihapus) </span>{{ run.t }}
            </span>
            <span
                v-else-if="run.o === 'ins'"
                class="rounded-sm bg-success/15 px-0.5 font-semibold underline decoration-success decoration-2 underline-offset-2"
            >
                <span class="sr-only">(baru) </span>{{ run.t }}
            </span>
            <span v-else>{{ run.t }}</span>
            {{ ' ' }}
        </template>
    </p>
</template>
