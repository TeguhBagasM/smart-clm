<script setup lang="ts">
import { ArrowDown, ArrowUp, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { FlowStep, FlowStepType } from '@/types';

const model = defineModel<FlowStep>({ required: true });

defineProps<{
    index: number;
    first: boolean;
    last: boolean;
    lanes: string[];
    targets: { value: string; label: string }[];
}>();

defineEmits<{ move: [step: -1 | 1]; remove: [] }>();

const types: { value: FlowStepType; label: string }[] = [
    { value: 'start', label: 'Mulai' },
    { value: 'process', label: 'Proses' },
    { value: 'decision', label: 'Keputusan' },
    { value: 'end', label: 'Selesai' },
];
const NONE = '__none';

function patch(values: Partial<FlowStep>): void {
    model.value = { ...model.value, ...values };
}

function pick(key: 'nextId' | 'yesId' | 'noId', value: unknown): void {
    patch({ [key]: value === NONE ? undefined : String(value) });
}
</script>

<template>
    <li class="flex flex-col gap-2 rounded-lg border p-3">
        <div class="flex items-center gap-1">
            <span class="w-6 shrink-0 text-sm text-muted-foreground"
                >{{ index + 1 }}.</span
            >
            <Input
                :model-value="model.text"
                placeholder="Teks langkah"
                :aria-label="`Teks langkah ${index + 1}`"
                @update:model-value="patch({ text: String($event) })"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Naikkan"
                :disabled="first"
                @click="$emit('move', -1)"
                ><ArrowUp
            /></Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Turunkan"
                :disabled="last"
                @click="$emit('move', 1)"
                ><ArrowDown
            /></Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Hapus langkah"
                @click="$emit('remove')"
                ><Trash2 class="text-destructive"
            /></Button>
        </div>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <Select
                :model-value="model.type"
                @update:model-value="patch({ type: $event as FlowStepType })"
            >
                <SelectTrigger aria-label="Jenis langkah"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="type in types"
                        :key="type.value"
                        :value="type.value"
                        >{{ type.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Select
                v-if="lanes.length"
                :model-value="model.actor || NONE"
                @update:model-value="
                    patch({
                        actor: $event === NONE ? undefined : String($event),
                    })
                "
            >
                <SelectTrigger aria-label="Pelaksana"
                    ><SelectValue placeholder="Pelaksana"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="NONE">Tanpa pelaksana</SelectItem>
                    <SelectItem
                        v-for="lane in lanes"
                        :key="lane"
                        :value="lane"
                        >{{ lane }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <template
                v-for="link in model.type === 'decision'
                    ? ([
                          ['yesId', 'Jika ya →'],
                          ['noId', 'Jika tidak →'],
                      ] as const)
                    : model.type === 'end'
                      ? []
                      : ([['nextId', 'Lanjut ke →']] as const)"
                :key="link[0]"
            >
                <Select
                    :model-value="model[link[0]] || NONE"
                    @update:model-value="pick(link[0], $event)"
                >
                    <SelectTrigger :aria-label="link[1]"
                        ><SelectValue :placeholder="link[1]"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="NONE"
                            >{{ link[1] }} (belum dipilih)</SelectItem
                        >
                        <SelectItem
                            v-for="target in targets"
                            :key="target.value"
                            :value="target.value"
                            >{{ target.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </template>
        </div>
    </li>
</template>
