<script setup lang="ts">
import { computed, ref } from 'vue';
import FlowCanvas from '@/components/documents/wizard/flow/FlowCanvas.vue';
import FlowDiagram from '@/components/documents/wizard/flow/FlowDiagram.vue';
import {
    download,
    svgToJpg,
} from '@/components/documents/wizard/flow/flowExport';
import { PRINT_ROWS } from '@/components/documents/wizard/flow/flowGeometry';
import { layoutFlow } from '@/components/documents/wizard/flow/flowLayout';
import FlowLanes from '@/components/documents/wizard/flow/FlowLanes.vue';
import FlowToolbar from '@/components/documents/wizard/flow/FlowToolbar.vue';
import { useFlowEditor } from '@/components/documents/wizard/flow/useFlowEditor';
import { Button } from '@/components/ui/button';
import { useWizard } from '@/composables/useDraftWizard';
import type { DraftFlow } from '@/types';

const { touch } = useWizard();
const editor = useFlowEditor();
const { state, layout } = editor;
const zoom = ref(1);
const error = ref('');
const exportSvg = ref<SVGSVGElement | null>(null);

/** The diagram as printed: no spare row, no selection. */
const printLayout = computed(() =>
    layoutFlow(state.flow.lanes, state.flow.steps, 0),
);
const tooTall = computed(() => layout.value.rows - 1 > PRINT_ROWS);

function setStyle(style: DraftFlow['style']): void {
    state.flow = { ...state.flow, style };
    touch('flow');
}

async function exportJpg(): Promise<void> {
    error.value = '';

    try {
        if (exportSvg.value)
            download(await svgToJpg(exportSvg.value), 'diagram-alir.jpg');
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Gagal mengunduh.';
    }
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <FlowLanes :editor="editor" />
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-y py-2">
            <div
                class="flex gap-1"
                role="radiogroup"
                aria-label="Tampilan di PDF"
            >
                <Button
                    v-for="style in ['diagram', 'table'] as const"
                    :key="style"
                    type="button"
                    size="sm"
                    role="radio"
                    :aria-checked="state.flow.style === style"
                    :variant="
                        state.flow.style === style ? 'secondary' : 'ghost'
                    "
                    @click="setStyle(style)"
                >
                    {{ style === 'diagram' ? 'Diagram' : 'Tabel' }}
                </Button>
            </div>
            <span class="h-5 w-px bg-border" aria-hidden="true" />
            <FlowToolbar
                v-model:zoom="zoom"
                :editor="editor"
                class="flex-1"
                @export="exportJpg"
            />
        </div>
        <p v-if="error" role="alert" class="text-sm text-destructive">
            {{ error }}
        </p>
        <FlowCanvas :editor="editor" :zoom="zoom" />
        <p v-if="tooTall" class="text-sm text-warning-foreground">
            Diagram lebih dari {{ PRINT_ROWS }} baris bisa terpotong di halaman
            PDF. Gabungkan langkah atau rapatkan baris.
        </p>
        <p class="text-xs text-muted-foreground">
            Klik simbol lalu pilih langkah berikutnya. Klik dua kali untuk
            mengubah teks, seret untuk memindah, Delete untuk menghapus, Ctrl+Z
            untuk mengurungkan. Garis dibuat otomatis seperti di PDF.
        </p>
        <div class="sr-only" aria-hidden="true">
            <FlowDiagram v-model:svg="exportSvg" :layout="printLayout" />
        </div>
    </div>
</template>
