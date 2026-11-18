<script setup lang="ts">
import { Download, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import StringListEditor from '@/components/documents/config/StringListEditor.vue';
import {
    download,
    svgToJpg,
} from '@/components/documents/wizard/flow/flowExport';
import FlowPreview from '@/components/documents/wizard/flow/FlowPreview.vue';
import FlowStepRow from '@/components/documents/wizard/flow/FlowStepRow.vue';
import { Button } from '@/components/ui/button';
import { useWizard } from '@/composables/useDraftWizard';
import { moveItem, removeItem } from '@/lib/arrays';
import type { DraftFlow, FlowStep } from '@/types';

const { state, touch } = useWizard();
const svg = ref<SVGSVGElement | null>(null);
const error = ref('');

const targets = computed(() =>
    state.flow.steps.map((step, i) => ({
        value: step.id,
        label: `${i + 1}. ${step.text || 'Tanpa teks'}`,
    })),
);

function change(patch: Partial<DraftFlow>): void {
    state.flow = { ...state.flow, ...patch };
    touch('flow');
}

function renumber(steps: FlowStep[]): void {
    change({ steps: steps.map((step, i) => ({ ...step, order: i + 1 })) });
}

function addStep(): void {
    const last = state.flow.steps.at(-1);
    const step: FlowStep = {
        id: crypto.randomUUID(),
        order: state.flow.steps.length + 1,
        type: last ? 'process' : 'start',
        text: '',
        actor: last?.actor ?? state.flow.lanes[0],
    };
    const linkable =
        last && last.type !== 'decision' && last.type !== 'end' && !last.nextId;

    renumber(
        linkable
            ? [
                  ...state.flow.steps.slice(0, -1),
                  { ...last, nextId: step.id },
                  step,
              ]
            : [...state.flow.steps, step],
    );
}

function drop(index: number): void {
    const id = state.flow.steps[index].id;
    const clean = (value?: string) => (value === id ? undefined : value);

    renumber(
        removeItem(state.flow.steps, index).map((step) => ({
            ...step,
            nextId: clean(step.nextId),
            yesId: clean(step.yesId),
            noId: clean(step.noId),
        })),
    );
}

async function exportJpg(): Promise<void> {
    error.value = '';

    try {
        if (svg.value) download(await svgToJpg(svg.value), 'diagram-alir.jpg');
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Gagal mengunduh.';
    }
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="flex flex-col gap-2">
            <h3 class="text-sm font-medium">Pelaksana (lajur)</h3>
            <StringListEditor
                :model-value="state.flow.lanes"
                label="Pelaksana"
                placeholder="mis. Staf, Manajer"
                @update:model-value="change({ lanes: $event })"
            />
        </div>
        <div class="flex gap-2">
            <Button
                v-for="style in ['diagram', 'table'] as const"
                :key="style"
                type="button"
                size="sm"
                :variant="state.flow.style === style ? 'default' : 'outline'"
                @click="change({ style })"
            >
                {{
                    style === 'diagram'
                        ? 'Tampil sebagai diagram'
                        : 'Tampil sebagai tabel'
                }}
            </Button>
        </div>
        <ol class="flex flex-col gap-3">
            <FlowStepRow
                v-for="(step, i) in state.flow.steps"
                :key="step.id"
                :model-value="step"
                :index="i"
                :first="i === 0"
                :last="i === state.flow.steps.length - 1"
                :lanes="state.flow.lanes"
                :targets="targets.filter((target) => target.value !== step.id)"
                @update:model-value="
                    renumber(
                        state.flow.steps.map((item, p) =>
                            p === i ? $event : item,
                        ),
                    )
                "
                @move="renumber(moveItem(state.flow.steps, i, $event))"
                @remove="drop(i)"
            />
        </ol>
        <Button
            type="button"
            variant="outline"
            class="self-start"
            @click="addStep"
        >
            <Plus /> Tambah langkah
        </Button>
        <div v-if="state.flow.steps.length" class="flex flex-col gap-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-medium">Pratinjau</h3>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="exportJpg"
                >
                    <Download /> Unduh JPG
                </Button>
            </div>
            <p v-if="error" role="alert" class="text-sm text-destructive">
                {{ error }}
            </p>
            <FlowPreview
                v-model:svg="svg"
                :lanes="state.flow.lanes"
                :steps="state.flow.steps"
            />
        </div>
    </div>
</template>
