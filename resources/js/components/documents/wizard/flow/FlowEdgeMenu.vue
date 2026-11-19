<script setup lang="ts">
import { ArrowLeftRight, Trash2, Wand2, X } from '@lucide/vue';
import { computed } from 'vue';
import type { DrawnEdge } from '@/components/documents/wizard/flow/flowLayout';
import {
    setPorts,
    swapBranches,
} from '@/components/documents/wizard/flow/flowLinkOps';
import { disconnect } from '@/components/documents/wizard/flow/flowOps';
import FlowSidePicker from '@/components/documents/wizard/flow/FlowSidePicker.vue';
import type { FlowEditor } from '@/components/documents/wizard/flow/useFlowEditor';
import { Button } from '@/components/ui/button';

const props = defineProps<{ editor: FlowEditor; edge: DrawnEdge }>();
const { layout, selection, apply } = props.editor;

const name = (id: string) => {
    const step = layout.value.steps.find((item) => item.id === id);

    return step?.text || `langkah ${step?.order ?? ''}`;
};
const label = computed(() => props.edge.label?.text);

function remove(): void {
    apply(disconnect(props.edge.from, props.edge.kind));
    selection.value = null;
}
</script>

<template>
    <div
        class="flex w-full flex-col gap-2 rounded-lg border bg-popover p-3 text-sm text-popover-foreground shadow-md md:w-80"
        role="dialog"
        aria-label="Garis"
        @pointerdown.stop
        @keydown.stop
    >
        <p class="text-muted-foreground">
            Dari "{{ name(edge.from) }}" ke "{{ name(edge.to) }}"<template
                v-if="label"
            >
                ({{ label }})</template
            >
        </p>
        <p v-if="edge.blocked" class="text-xs text-destructive">
            Garis ini menembus simbol lain. Geser bagian garisnya atau pilih
            Atur otomatis.
        </p>
        <p v-else-if="!edge.manual" class="text-xs text-muted-foreground">
            Seret kotak kecil di tengah garis untuk menggesernya, atau ujung
            garis ke titik lain.
        </p>
        <FlowSidePicker
            label="Keluar dari"
            :value="edge.exit"
            @change="apply(setPorts(edge.from, edge.kind, { exit: $event }))"
        />
        <FlowSidePicker
            label="Masuk ke"
            :value="edge.entry"
            @change="apply(setPorts(edge.from, edge.kind, { entry: $event }))"
        />
        <div class="flex flex-wrap gap-1">
            <Button
                v-if="edge.manual"
                type="button"
                variant="outline"
                size="sm"
                @click="
                    apply(
                        setPorts(edge.from, edge.kind, {
                            exit: null,
                            entry: null,
                        }),
                    )
                "
            >
                <Wand2 /> Atur otomatis
            </Button>
            <Button
                v-if="edge.kind !== 'next'"
                type="button"
                variant="outline"
                size="sm"
                @click="apply(swapBranches(edge.from))"
            >
                <ArrowLeftRight /> Tukar Ya / Tidak
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="text-destructive"
                @click="remove"
                ><Trash2 /> Hapus garis</Button
            >
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="ml-auto size-8"
                aria-label="Tutup"
                @click="selection = null"
                ><X
            /></Button>
        </div>
    </div>
</template>
