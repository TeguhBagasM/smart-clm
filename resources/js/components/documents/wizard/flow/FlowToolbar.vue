<script setup lang="ts">
import {
    Download,
    Minus,
    Plus,
    Redo2,
    Rows3,
    Undo2,
    ZoomIn,
    ZoomOut,
} from '@lucide/vue';
import {
    insertRow,
    removeRow,
} from '@/components/documents/wizard/flow/flowGridOps';
import type { FlowEditor } from '@/components/documents/wizard/flow/useFlowEditor';
import { Button } from '@/components/ui/button';

const props = defineProps<{ editor: FlowEditor }>();
const zoom = defineModel<number>('zoom', { required: true });
defineEmits<{ export: [] }>();

const { selectedStep, apply, undo, redo, canUndo, canRedo } = props.editor;
const ZOOMS = [0.5, 0.75, 1, 1.25, 1.5];

function stepZoom(by: 1 | -1): void {
    const i = ZOOMS.indexOf(zoom.value);
    zoom.value =
        ZOOMS[Math.min(ZOOMS.length - 1, Math.max(0, (i < 0 ? 2 : i) + by))];
}
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-1"
        role="toolbar"
        aria-label="Alat diagram"
    >
        <Button
            type="button"
            variant="ghost"
            size="icon"
            :disabled="!canUndo"
            title="Urungkan (Ctrl+Z)"
            aria-label="Urungkan"
            @click="undo"
            ><Undo2
        /></Button>
        <Button
            type="button"
            variant="ghost"
            size="icon"
            :disabled="!canRedo"
            title="Ulangi (Ctrl+Shift+Z)"
            aria-label="Ulangi"
            @click="redo"
            ><Redo2
        /></Button>
        <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />
        <Button
            type="button"
            variant="ghost"
            size="icon"
            title="Perkecil"
            aria-label="Perkecil"
            @click="stepZoom(-1)"
            ><ZoomOut
        /></Button>
        <span
            class="w-12 text-center text-xs text-muted-foreground tabular-nums"
            >{{ Math.round(zoom * 100) }}%</span
        >
        <Button
            type="button"
            variant="ghost"
            size="icon"
            title="Perbesar"
            aria-label="Perbesar"
            @click="stepZoom(1)"
            ><ZoomIn
        /></Button>
        <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />
        <Button
            type="button"
            variant="ghost"
            size="sm"
            :disabled="!selectedStep"
            title="Sisipkan baris kosong di atas langkah terpilih"
            @click="selectedStep && apply(insertRow(selectedStep.row))"
        >
            <Rows3 /><Plus class="-ml-1 size-3" /> Baris
        </Button>
        <Button
            type="button"
            variant="ghost"
            size="sm"
            :disabled="!selectedStep || selectedStep.row === 0"
            title="Hapus baris kosong di atas langkah terpilih"
            @click="selectedStep && apply(removeRow(selectedStep.row - 1))"
        >
            <Rows3 /><Minus class="-ml-1 size-3" /> Baris
        </Button>
        <Button
            type="button"
            variant="outline"
            size="sm"
            class="ml-auto"
            @click="$emit('export')"
            ><Download /> Unduh JPG</Button
        >
    </div>
</template>
