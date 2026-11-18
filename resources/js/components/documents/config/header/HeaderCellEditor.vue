<script setup lang="ts">
import { Bold, Italic, Trash2, CaseUpper } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { HeaderCell, HeaderFieldOption } from '@/types';

const model = defineModel<HeaderCell>({ required: true });

defineProps<{
    fields: HeaderFieldOption[];
    index: number;
    removable: boolean;
}>();
defineEmits<{ remove: [] }>();

function patch(values: Partial<HeaderCell>): void {
    model.value = { ...model.value, ...values };
}

const toggles = [
    { key: 'bold', label: 'Tebal', icon: Bold },
    { key: 'italic', label: 'Miring', icon: Italic },
    { key: 'uppercase', label: 'Huruf besar', icon: CaseUpper },
] as const;
</script>

<template>
    <li class="flex flex-col gap-2 rounded-md border bg-background p-2">
        <div class="grid gap-2 sm:grid-cols-[1fr_1fr_4.5rem_auto]">
            <Select
                :model-value="model.field"
                @update:model-value="patch({ field: String($event) })"
            >
                <SelectTrigger
                    class="w-full"
                    :aria-label="`Isian sel ${index + 1}`"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="f in fields"
                        :key="f.value"
                        :value="f.value"
                    >
                        {{ f.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Input
                :model-value="model.label"
                :placeholder="
                    model.field === 'text' ? 'Teks' : 'Label (boleh kosong)'
                "
                :aria-label="`Label sel ${index + 1}`"
                maxlength="60"
                @update:model-value="patch({ label: String($event) })"
            />
            <label
                class="flex items-center gap-2 text-xs text-muted-foreground sm:block"
            >
                <span class="sm:sr-only">Lebar (dari 12)</span>
                <Input
                    type="number"
                    min="1"
                    max="12"
                    :model-value="model.span"
                    :aria-label="`Lebar sel ${index + 1} (dari 12)`"
                    title="Lebar, dari 12 bagian"
                    @update:model-value="
                        patch({
                            span: Math.min(
                                12,
                                Math.max(1, Number($event) || 1),
                            ),
                        })
                    "
                />
            </label>
            <Button
                v-if="removable"
                type="button"
                variant="ghost"
                size="icon"
                :aria-label="`Hapus sel ${index + 1}`"
                @click="$emit('remove')"
            >
                <Trash2 class="text-destructive" />
            </Button>
        </div>
        <div class="flex flex-wrap items-center gap-1">
            <Button
                v-for="t in toggles"
                :key="t.key"
                type="button"
                size="sm"
                :variant="model[t.key] ? 'default' : 'outline'"
                :aria-pressed="model[t.key]"
                :aria-label="t.label"
                @click="patch({ [t.key]: !model[t.key] })"
            >
                <component :is="t.icon" />
            </Button>
            <Select
                :model-value="model.align"
                @update:model-value="
                    patch({ align: $event as HeaderCell['align'] })
                "
            >
                <SelectTrigger size="sm" class="w-28" aria-label="Perataan">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="left">Rata kiri</SelectItem>
                    <SelectItem value="center">Tengah</SelectItem>
                    <SelectItem value="right">Rata kanan</SelectItem>
                </SelectContent>
            </Select>
            <Select
                :model-value="model.fill"
                @update:model-value="
                    patch({ fill: $event as HeaderCell['fill'] })
                "
            >
                <SelectTrigger size="sm" class="w-32" aria-label="Warna latar">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">Tanpa latar</SelectItem>
                    <SelectItem value="muted">Latar abu</SelectItem>
                    <SelectItem value="dark">Latar biru tua</SelectItem>
                </SelectContent>
            </Select>
        </div>
    </li>
</template>
