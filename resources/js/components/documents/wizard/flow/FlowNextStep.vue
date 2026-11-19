<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { appendStep } from '@/components/documents/wizard/flow/flowGridOps';
import type { PlacedStep } from '@/components/documents/wizard/flow/flowLayout';
import FlowShape from '@/components/documents/wizard/flow/FlowShape.vue';
import { NEXT_SYMBOLS } from '@/components/documents/wizard/flow/flowSymbols';
import type { FlowEditor } from '@/components/documents/wizard/flow/useFlowEditor';
import type { FlowStepType } from '@/types';

const props = defineProps<{ editor: FlowEditor; step: PlacedStep }>();
const { layout, selection, editing, apply } = props.editor;

const isDecision = computed(() => props.step.type === 'decision');
const branch = ref<'yes' | 'no'>('yes');
const lane = ref(0);

/** Tidak usually goes sideways, so it starts on the next lane when there is one. */
function defaultLane(which: 'yes' | 'no'): number {
    const lanes = layout.value.lanes.length;

    if (which === 'yes' || lanes < 2) {
        return props.step.lane;
    }

    return props.step.lane + 1 < lanes
        ? props.step.lane + 1
        : props.step.lane - 1;
}

watch(
    () => props.step.id,
    () => {
        branch.value = props.step.yesId && !props.step.noId ? 'no' : 'yes';
        lane.value = defaultLane(branch.value);
    },
    { immediate: true },
);
watch(branch, (which) => (lane.value = defaultLane(which)));

function add(type: FlowStepType): void {
    const id = crypto.randomUUID();
    const kind = isDecision.value ? branch.value : 'next';

    if (apply(appendStep(props.step.id, type, lane.value, kind, id))) {
        selection.value = { kind: 'step', id };
        editing.value = type === 'end' ? null : id;
    }
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <p class="text-xs font-medium text-muted-foreground">
            Langkah berikutnya
        </p>
        <div
            v-if="isDecision"
            class="grid grid-cols-2 gap-1 rounded-md bg-muted p-0.5"
            role="radiogroup"
            aria-label="Cabang keputusan"
        >
            <button
                v-for="item in [
                    ['yes', 'Jika Ya', step.yesId],
                    ['no', 'Jika Tidak', step.noId],
                ] as const"
                :key="item[0]"
                type="button"
                role="radio"
                :aria-checked="branch === item[0]"
                class="rounded-sm px-2 py-1 text-xs aria-checked:bg-card aria-checked:font-medium aria-checked:shadow-sm"
                @click="branch = item[0]"
            >
                {{ item[1]
                }}<span v-if="item[2]" class="text-muted-foreground"> ✓</span>
            </button>
        </div>
        <div v-if="layout.lanes.length > 1" class="flex flex-wrap gap-1">
            <button
                v-for="(name, i) in layout.lanes"
                :key="i"
                type="button"
                :aria-pressed="lane === i"
                class="rounded-full border px-2 py-0.5 text-xs aria-pressed:border-primary aria-pressed:bg-accent aria-pressed:text-primary"
                @click="lane = i"
            >
                {{ name }}
            </button>
        </div>
        <div class="grid grid-cols-3 gap-1">
            <button
                v-for="symbol in NEXT_SYMBOLS"
                :key="symbol.type"
                type="button"
                class="flex min-h-10 flex-col items-center gap-1 rounded-md border px-1 py-1.5 text-xs hover:border-primary hover:bg-accent"
                :title="`Tambah ${symbol.label} setelah langkah ini`"
                @click="add(symbol.type)"
            >
                <svg viewBox="-2 -2 64 30" class="h-4 w-9" aria-hidden="true">
                    <FlowShape
                        :type="symbol.type"
                        :box="{ x: 0, y: 0, w: 60, h: 26 }"
                    />
                </svg>
                {{ symbol.label }}
            </button>
        </div>
    </div>
</template>
