<script setup lang="ts">
import { onMounted, ref } from 'vue';
import type { PlacedStep } from '@/components/documents/wizard/flow/flowLayout';
import { updateStep } from '@/components/documents/wizard/flow/flowOps';
import type { FlowEditor } from '@/components/documents/wizard/flow/useFlowEditor';

const props = defineProps<{
    editor: FlowEditor;
    step: PlacedStep;
    zoom: number;
}>();
const { editing, apply } = props.editor;
const field = ref<HTMLTextAreaElement | null>(null);
const text = ref(props.step.text ?? '');
let closed = false;

onMounted(() => {
    field.value?.focus();
    field.value?.select();
});

function close(save: boolean): void {
    if (closed) return;

    closed = true;

    if (save && text.value !== (props.step.text ?? '')) {
        apply(updateStep(props.step.id, { text: text.value.trim() }));
    }

    editing.value = null;
}

function key(event: KeyboardEvent): void {
    event.stopPropagation();

    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        close(true);
    } else if (event.key === 'Escape') {
        close(false);
    }
}
</script>

<template>
    <textarea
        ref="field"
        v-model="text"
        maxlength="300"
        rows="2"
        aria-label="Teks langkah"
        placeholder="Tulis langkah, Enter untuk simpan"
        class="absolute z-20 resize-none rounded-md border-2 border-primary bg-card p-1 text-center text-xs leading-tight shadow-md outline-none"
        :style="{
            left: `${(step.box.x - 6) * zoom}px`,
            top: `${(step.box.y - 4) * zoom}px`,
            width: `${(step.box.w + 12) * zoom}px`,
            height: `${(step.box.h + 8) * zoom}px`,
        }"
        @keydown="key"
        @pointerdown.stop
        @blur="close(true)"
    />
</template>
