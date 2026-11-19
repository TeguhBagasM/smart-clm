<script setup lang="ts">
import { ChevronLeft, ChevronRight, Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    addLane,
    moveLane,
    removeLane,
    renameLane,
} from '@/components/documents/wizard/flow/flowGridOps';
import type { FlowEditor } from '@/components/documents/wizard/flow/useFlowEditor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{ editor: FlowEditor }>();
const { state, apply } = props.editor;
const draft = ref('');

function add(): void {
    if (apply(addLane(draft.value))) {
        draft.value = '';
    }
}

function rename(index: number, event: Event): void {
    const input = event.target as HTMLInputElement;

    if (!apply(renameLane(index, input.value))) {
        input.value = state.flow.lanes[index];
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-sm font-medium">Pelaksana</span>
        <ul v-if="state.flow.lanes.length" class="flex flex-wrap gap-2">
            <li
                v-for="(lane, i) in state.flow.lanes"
                :key="lane"
                class="flex items-center gap-0.5 rounded-md border bg-secondary p-0.5"
            >
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-6"
                    :disabled="i === 0"
                    :aria-label="`Geser ${lane} ke kiri`"
                    @click="apply(moveLane(i, -1))"
                >
                    <ChevronLeft class="size-3.5" />
                </Button>
                <input
                    :value="lane"
                    :aria-label="`Nama lajur ${i + 1}`"
                    maxlength="100"
                    class="w-28 rounded-sm bg-transparent px-1 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/70"
                    @change="rename(i, $event)"
                    @keydown.enter.prevent="
                        ($event.target as HTMLInputElement).blur()
                    "
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-6"
                    :disabled="i === state.flow.lanes.length - 1"
                    :aria-label="`Geser ${lane} ke kanan`"
                    @click="apply(moveLane(i, 1))"
                >
                    <ChevronRight class="size-3.5" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-6"
                    :aria-label="`Hapus lajur ${lane}`"
                    @click="apply(removeLane(i))"
                >
                    <X class="size-3.5" />
                </Button>
            </li>
        </ul>
        <div class="flex w-full gap-1 sm:w-auto">
            <Input
                v-model="draft"
                class="h-8 sm:w-52"
                placeholder="Tambah pelaksana, mis. HRD"
                aria-label="Pelaksana baru"
                maxlength="100"
                @keydown.enter.prevent="add"
            />
            <Button
                type="button"
                variant="outline"
                size="icon"
                class="size-8 shrink-0"
                aria-label="Tambah pelaksana"
                @click="add"
                ><Plus
            /></Button>
        </div>
    </div>
</template>
