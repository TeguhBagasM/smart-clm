<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import HeaderCellEditor from '@/components/documents/config/header/HeaderCellEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { removeItem } from '@/lib/arrays';
import type { HeaderCell, HeaderFieldOption, HeaderRow } from '@/types';

const model = defineModel<HeaderRow>({ required: true });

defineProps<{
    fields: HeaderFieldOption[];
    index: number;
    first: boolean;
    last: boolean;
    removable: boolean;
    beside: boolean;
}>();
defineEmits<{ move: [step: -1 | 1]; remove: [] }>();

const MAX_CELLS = 4;

function addCell(): void {
    const cell: HeaderCell = {
        span: 6,
        label: '',
        field: 'text',
        bold: false,
        italic: false,
        uppercase: false,
        align: 'left',
        fill: 'none',
    };
    model.value = { ...model.value, cells: [...model.value.cells, cell] };
}
</script>

<template>
    <li class="flex flex-col gap-2 rounded-lg border p-3">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium">Baris {{ index + 1 }}</span>
            <span v-if="beside" class="text-xs text-muted-foreground"
                >di samping logo</span
            >
            <label
                class="ml-auto flex items-center gap-1 text-xs text-muted-foreground"
            >
                Tinggi
                <Input
                    type="number"
                    min="12"
                    max="60"
                    class="h-8 w-16"
                    :model-value="model.height"
                    :aria-label="`Tinggi baris ${index + 1}`"
                    @update:model-value="
                        model = {
                            ...model,
                            height: Math.min(
                                60,
                                Math.max(12, Number($event) || 18),
                            ),
                        }
                    "
                />
            </label>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Naikkan baris"
                :disabled="first"
                @click="$emit('move', -1)"
                ><ArrowUp
            /></Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Turunkan baris"
                :disabled="last"
                @click="$emit('move', 1)"
                ><ArrowDown
            /></Button>
            <Button
                v-if="removable"
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Hapus baris"
                @click="$emit('remove')"
                ><Trash2 class="text-destructive"
            /></Button>
        </div>
        <ul class="flex flex-col gap-2">
            <HeaderCellEditor
                v-for="(cell, cellIndex) in model.cells"
                :key="cellIndex"
                :model-value="cell"
                :fields="fields"
                :index="cellIndex"
                :removable="model.cells.length > 1"
                @update:model-value="
                    model = {
                        ...model,
                        cells: model.cells.map((c, p) =>
                            p === cellIndex ? $event : c,
                        ),
                    }
                "
                @remove="
                    model = {
                        ...model,
                        cells: removeItem(model.cells, cellIndex),
                    }
                "
            />
        </ul>
        <Button
            v-if="model.cells.length < MAX_CELLS"
            type="button"
            variant="outline"
            size="sm"
            class="self-start"
            @click="addCell"
        >
            <Plus /> Tambah sel
        </Button>
    </li>
</template>
