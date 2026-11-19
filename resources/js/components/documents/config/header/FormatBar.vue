<script setup lang="ts">
import { Redo2, Undo2 } from '@lucide/vue';
import CellToolbar from '@/components/documents/config/header/CellToolbar.vue';
import PresetMenu from '@/components/documents/config/header/PresetMenu.vue';
import ZoomControl, {
    type Zoom,
} from '@/components/documents/config/header/ZoomControl.vue';
import { Button } from '@/components/ui/button';
import type { HeaderCell, HeaderPreset } from '@/types';

/** The row above the canvas: formatting of the selected cell, then undo, presets and zoom. */
const zoom = defineModel<Zoom>('zoom', { required: true });

defineProps<{
    cell: HeaderCell | null;
    canDelete: boolean;
    canUndo: boolean;
    canRedo: boolean;
    presets: HeaderPreset[];
}>();
defineEmits<{
    patch: [values: Partial<HeaderCell>];
    remove: [];
    undo: [];
    redo: [];
    preset: [preset: HeaderPreset];
}>();
</script>

<template>
    <div class="flex min-h-11 flex-wrap items-center justify-between gap-2">
        <CellToolbar
            v-if="cell"
            :cell="cell"
            :can-delete="canDelete"
            @patch="$emit('patch', $event)"
            @remove="$emit('remove')"
        />
        <p v-else class="text-sm text-muted-foreground">
            Pilih sel di kop untuk memformat teksnya.
        </p>
        <div class="flex flex-wrap items-center gap-1">
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="!canUndo"
                title="Urungkan (Ctrl/Cmd+Z)"
                @click="$emit('undo')"
                ><Undo2 /> Undo</Button
            >
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="!canRedo"
                title="Ulangi (Ctrl/Cmd+Shift+Z)"
                @click="$emit('redo')"
                ><Redo2 /> Redo</Button
            >
            <PresetMenu :presets="presets" @apply="$emit('preset', $event)" />
            <ZoomControl v-model="zoom" />
        </div>
    </div>
</template>
