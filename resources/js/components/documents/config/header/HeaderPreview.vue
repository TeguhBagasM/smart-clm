<script setup lang="ts">
import { computed } from 'vue';
import HeaderPreviewRow from '@/components/documents/config/header/HeaderPreviewRow.vue';
import type { HeaderFieldOption, HeaderLayout } from '@/types';

const props = defineProps<{
    layout: HeaderLayout;
    fields: HeaderFieldOption[];
}>();

const samples = computed(() =>
    Object.fromEntries(props.fields.map((f) => [f.value, f.sample])),
);
const beside = computed(() =>
    props.layout.logo.enabled
        ? Math.min(props.layout.logo.rows, props.layout.rows.length)
        : 0,
);
</script>

<template>
    <div
        class="overflow-hidden rounded-md border border-foreground/40 bg-card"
        role="img"
        aria-label="Pratinjau kop surat"
    >
        <div v-if="beside" class="flex">
            <div
                class="flex items-center justify-center border-r border-b border-foreground/40 text-xs text-muted-foreground"
                :style="{ width: `${layout.logo.width}%` }"
            >
                Logo
            </div>
            <div class="min-w-0 flex-1">
                <HeaderPreviewRow
                    v-for="(row, index) in layout.rows.slice(0, beside)"
                    :key="index"
                    :row="row"
                    :samples="samples"
                />
            </div>
        </div>
        <HeaderPreviewRow
            v-for="(row, index) in layout.rows.slice(beside)"
            :key="beside + index"
            :row="row"
            :samples="samples"
        />
    </div>
</template>
