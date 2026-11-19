<script setup lang="ts">
import { Link2, Pencil, Trash2, X } from '@lucide/vue';
import FlowNextStep from '@/components/documents/wizard/flow/FlowNextStep.vue';
import type { PlacedStep } from '@/components/documents/wizard/flow/flowLayout';
import { setType } from '@/components/documents/wizard/flow/flowLinkOps';
import { removeStep } from '@/components/documents/wizard/flow/flowOps';
import {
    TYPE_LABELS,
    typeOptions,
} from '@/components/documents/wizard/flow/flowSymbols';
import type { FlowEditor } from '@/components/documents/wizard/flow/useFlowEditor';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { FlowStepType } from '@/types';

const props = defineProps<{ editor: FlowEditor; step: PlacedStep }>();
const { selection, editing, linking, apply } = props.editor;

function remove(): void {
    apply(removeStep(props.step.id));
    selection.value = null;
}
</script>

<template>
    <div
        class="flex w-full flex-col gap-3 rounded-lg border bg-popover p-3 text-popover-foreground shadow-md md:w-72"
        role="dialog"
        :aria-label="`Langkah ${step.order}`"
        @pointerdown.stop
        @keydown.stop
    >
        <div class="flex items-center gap-1">
            <Select
                :model-value="step.type"
                @update:model-value="
                    apply(setType(step.id, $event as FlowStepType))
                "
            >
                <SelectTrigger
                    size="sm"
                    class="h-8 flex-1"
                    aria-label="Ganti simbol"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="type in typeOptions(step.type)"
                        :key="type"
                        :value="type"
                        >{{ TYPE_LABELS[type] }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-8"
                title="Ubah teks (Enter)"
                aria-label="Ubah teks"
                @click="editing = step.id"
            >
                <Pencil />
            </Button>
            <Button
                v-if="step.type !== 'end'"
                type="button"
                variant="ghost"
                size="icon"
                class="size-8"
                title="Hubungkan ke langkah yang sudah ada"
                aria-label="Hubungkan ke langkah yang sudah ada"
                @click="linking = step.id"
            >
                <Link2 />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-8 text-destructive"
                title="Hapus langkah (Delete)"
                aria-label="Hapus langkah"
                @click="remove"
            >
                <Trash2 />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-8"
                aria-label="Tutup"
                @click="selection = null"
            >
                <X />
            </Button>
        </div>
        <FlowNextStep
            v-if="step.type !== 'end'"
            :editor="editor"
            :step="step"
        />
        <p v-else class="text-xs text-muted-foreground">
            Selesai adalah akhir alur, tidak ada langkah berikutnya.
        </p>
    </div>
</template>
