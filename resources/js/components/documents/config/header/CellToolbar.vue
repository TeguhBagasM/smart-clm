<script setup lang="ts">
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Bold,
    CaseUpper,
    Italic,
    Trash2,
} from '@lucide/vue';
import ColorPicker from '@/components/documents/config/header/color/ColorPicker.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { HeaderCell } from '@/types';

const props = defineProps<{ cell: HeaderCell; canDelete: boolean }>();
const emit = defineEmits<{
    patch: [values: Partial<HeaderCell>];
    remove: [];
}>();

const toggles = [
    { key: 'bold', label: 'Tebal', icon: Bold },
    { key: 'italic', label: 'Miring', icon: Italic },
    { key: 'uppercase', label: 'HURUF BESAR', icon: CaseUpper },
] as const;
const aligns = [
    { value: 'left', label: 'Rata kiri', icon: AlignLeft },
    { value: 'center', label: 'Rata tengah', icon: AlignCenter },
    { value: 'right', label: 'Rata kanan', icon: AlignRight },
] as const;
const sizes = [6, 7, 8, 9, 10, 11, 12, 14];
</script>

<template>
    <div
        class="flex w-max max-w-full flex-wrap items-center gap-1 rounded-md border bg-card p-1"
        role="toolbar"
        aria-label="Format sel"
        @click.stop
    >
        <Button
            v-for="t in toggles"
            :key="t.key"
            type="button"
            size="icon"
            class="size-8"
            :variant="props.cell[t.key] ? 'default' : 'ghost'"
            :aria-pressed="props.cell[t.key]"
            :aria-label="t.label"
            :title="t.label"
            @click="emit('patch', { [t.key]: !props.cell[t.key] })"
        >
            <component :is="t.icon" />
        </Button>
        <span class="mx-0.5 h-5 w-px bg-border" />
        <Button
            v-for="a in aligns"
            :key="a.value"
            type="button"
            size="icon"
            class="size-8"
            :variant="props.cell.align === a.value ? 'default' : 'ghost'"
            :aria-pressed="props.cell.align === a.value"
            :aria-label="a.label"
            :title="a.label"
            @click="emit('patch', { align: a.value })"
        >
            <component :is="a.icon" />
        </Button>
        <span class="mx-0.5 h-5 w-px bg-border" />
        <Select
            :model-value="String(props.cell.fontSize ?? 'auto')"
            @update:model-value="
                emit('patch', {
                    fontSize: $event === 'auto' ? null : Number($event),
                })
            "
        >
            <SelectTrigger size="sm" class="w-28" aria-label="Ukuran huruf"
                ><SelectValue
            /></SelectTrigger>
            <SelectContent>
                <SelectItem value="auto">Otomatis</SelectItem>
                <SelectItem v-for="s in sizes" :key="s" :value="String(s)"
                    >{{ s }} pt</SelectItem
                >
            </SelectContent>
        </Select>
        <ColorPicker
            :model-value="props.cell.color"
            label="Teks"
            allow-auto
            @update:model-value="emit('patch', { color: $event ?? 'auto' })"
        />
        <ColorPicker
            :model-value="props.cell.background"
            label="Latar"
            allow-none
            @update:model-value="emit('patch', { background: $event })"
        />
        <Button
            type="button"
            variant="ghost"
            size="icon"
            class="size-8"
            :disabled="!canDelete"
            aria-label="Hapus sel"
            title="Hapus sel"
            @click="emit('remove')"
        >
            <Trash2 class="text-destructive" />
        </Button>
    </div>
</template>
