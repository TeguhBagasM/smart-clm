<script setup lang="ts">
import { Play } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref } from 'vue';
import FlowDiagram from '@/components/documents/wizard/flow/FlowDiagram.vue';
import FlowEdgeMenu from '@/components/documents/wizard/flow/FlowEdgeMenu.vue';
import {
    edgeMenuStyle,
    stepMenuStyle,
} from '@/components/documents/wizard/flow/flowMenuPosition';
import { addStep } from '@/components/documents/wizard/flow/flowOps';
import FlowStepMenu from '@/components/documents/wizard/flow/FlowStepMenu.vue';
import FlowTextEditor from '@/components/documents/wizard/flow/FlowTextEditor.vue';
import { useCanvasKeys } from '@/components/documents/wizard/flow/useCanvasKeys';
import { useCanvasPointer } from '@/components/documents/wizard/flow/useCanvasPointer';
import type { FlowEditor } from '@/components/documents/wizard/flow/useFlowEditor';
import { Button } from '@/components/ui/button';

const props = defineProps<{ editor: FlowEditor; zoom: number }>();

const svg = ref<SVGSVGElement | null>(null);
const wide = useMediaQuery('(min-width: 768px)');
const { layout, selection, selectedStep, editing, linking, apply } =
    props.editor;
const { drag, target, handlers } = useCanvasPointer(props.editor, svg);
const key = useCanvasKeys(props.editor);

const editingStep = computed(() =>
    layout.value.steps.find((step) => step.id === editing.value),
);
const edge = computed(() => {
    const current = selection.value;

    return current?.kind === 'edge'
        ? layout.value.edges.find(
              (e) => e.from === current.from && e.kind === current.link,
          )
        : undefined;
});
const showStepMenu = computed(
    () => selectedStep.value && !editing.value && !linking.value && !drag.value,
);
const handle = computed(() => {
    const step = selectedStep.value;

    return step && step.type !== 'end' && !editing.value
        ? { x: step.box.cx, y: step.box.y + step.box.h + 2 }
        : null;
});

function begin(): void {
    const id = crypto.randomUUID();

    if (apply(addStep('start', 0, 0, id))) {
        selection.value = { kind: 'step', id };
    }
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            class="overflow-auto rounded-lg border bg-card outline-none focus-visible:ring-2 focus-visible:ring-ring/70"
            tabindex="0"
            aria-label="Kanvas diagram alir. Klik simbol untuk menambah langkah berikutnya, seret untuk memindah, Delete untuk menghapus."
            @keydown="key"
        >
            <div
                class="relative min-h-60"
                :style="{
                    width: `${layout.width * zoom}px`,
                    height: `${layout.height * zoom}px`,
                }"
            >
                <FlowDiagram
                    v-model:svg="svg"
                    :layout="layout"
                    :selected-step="
                        selection?.kind === 'step' ? selection.id : undefined
                    "
                    :selected-edge="
                        edge ? `${edge.from}|${edge.kind}` : undefined
                    "
                    :width="layout.width * zoom"
                    :height="layout.height * zoom"
                    :class="[
                        'select-none',
                        linking ? 'cursor-crosshair' : undefined,
                    ]"
                    grid
                    v-on="handlers"
                >
                    <template #under>
                        <rect
                            v-if="target"
                            :x="target.box.x - 6"
                            :y="target.box.y - 6"
                            :width="target.box.w + 12"
                            :height="target.box.h + 12"
                            rx="8"
                            :fill="
                                target.free
                                    ? 'var(--success)'
                                    : 'var(--destructive)'
                            "
                            fill-opacity="0.18"
                            :stroke="
                                target.free
                                    ? 'var(--success)'
                                    : 'var(--destructive)'
                            "
                            stroke-dasharray="5 4"
                        />
                    </template>
                    <line
                        v-if="drag?.mode === 'link' && handle"
                        :x1="handle.x"
                        :y1="handle.y"
                        :x2="drag.x"
                        :y2="drag.y"
                        stroke="var(--primary)"
                        stroke-width="2"
                        stroke-dasharray="5 4"
                        pointer-events="none"
                    />
                    <g
                        v-if="handle"
                        data-handle
                        class="cursor-crosshair touch-none"
                    >
                        <circle
                            :cx="handle.x"
                            :cy="handle.y"
                            r="14"
                            fill="transparent"
                        />
                        <circle
                            :cx="handle.x"
                            :cy="handle.y"
                            r="6"
                            fill="var(--primary)"
                            stroke="var(--card)"
                            stroke-width="2"
                        >
                            <title>
                                Tarik ke simbol lain untuk menghubungkan
                            </title>
                        </circle>
                    </g>
                </FlowDiagram>
                <div
                    v-if="!layout.steps.length"
                    class="absolute inset-0 flex items-center justify-center p-4"
                >
                    <Button type="button" @click="begin"
                        ><Play /> Mulai buat diagram</Button
                    >
                </div>
                <FlowTextEditor
                    v-if="editingStep"
                    :key="editingStep.id"
                    :editor="editor"
                    :step="editingStep"
                    :zoom="zoom"
                />
                <div
                    v-if="wide && showStepMenu && selectedStep"
                    class="absolute z-10"
                    :style="stepMenuStyle(selectedStep.box, layout.width, zoom)"
                >
                    <FlowStepMenu :editor="editor" :step="selectedStep" />
                </div>
                <div
                    v-if="wide && edge"
                    class="absolute z-10"
                    :style="edgeMenuStyle(edge, zoom)"
                >
                    <FlowEdgeMenu :editor="editor" :edge="edge" />
                </div>
            </div>
        </div>
        <p v-if="linking" role="status" class="text-sm text-primary">
            Klik simbol tujuan untuk menghubungkan. Esc untuk batal.
        </p>
        <FlowStepMenu
            v-if="!wide && showStepMenu && selectedStep"
            :editor="editor"
            :step="selectedStep"
        />
        <FlowEdgeMenu v-if="!wide && edge" :editor="editor" :edge="edge" />
    </div>
</template>
