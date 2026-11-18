<script setup lang="ts">
import { ArrowDown, ArrowUp, Bold, List, Table2, Trash2 } from '@lucide/vue';
import { nextTick, ref } from 'vue';
import TableEditor from '@/components/documents/wizard/steps/TableEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { bulletLines, wrapSelection } from '@/lib/textFormat';
import type { Edit } from '@/lib/textFormat';
import type { DraftSection } from '@/types';

const model = defineModel<DraftSection>({ required: true });

defineProps<{ index: number; first: boolean; last: boolean }>();

defineEmits<{ move: [step: -1 | 1]; remove: [] }>();

const area = ref<{ $el: HTMLTextAreaElement } | null>(null);

/** Applies a formatting edit and puts the selection back where it was. */
async function apply(make: (el: HTMLTextAreaElement) => Edit): Promise<void> {
    const el = area.value?.$el;

    if (!el) return;

    const edit = make(el);
    model.value = { ...model.value, content: edit.value };
    await nextTick();
    el.focus();
    el.setSelectionRange(edit.start, edit.end);
}
</script>

<template>
    <li class="flex flex-col gap-2 rounded-lg border p-3">
        <div class="flex items-center gap-1">
            <span class="w-6 shrink-0 text-sm text-muted-foreground"
                >{{ index + 1 }}.</span
            >
            <Input
                :model-value="model.heading"
                placeholder="Judul bagian, mis. Definisi"
                :aria-label="`Judul bagian ${index + 1}`"
                @update:model-value="
                    model = { ...model, heading: String($event) }
                "
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Naikkan"
                :disabled="first"
                @click="$emit('move', -1)"
            >
                <ArrowUp />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Turunkan"
                :disabled="last"
                @click="$emit('move', 1)"
            >
                <ArrowDown />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Hapus bagian"
                @click="$emit('remove')"
            >
                <Trash2 class="text-destructive" />
            </Button>
        </div>
        <div class="flex flex-wrap items-center gap-1">
            <Button
                type="button"
                variant="outline"
                size="sm"
                aria-label="Tebal"
                @click="apply((el) => wrapSelection(el, '**', 'teks tebal'))"
            >
                <Bold />
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                aria-label="Daftar"
                @click="apply(bulletLines)"
            >
                <List /> Daftar
            </Button>
            <Button
                v-if="!model.table"
                type="button"
                variant="outline"
                size="sm"
                @click="
                    model = {
                        ...model,
                        table: { columns: ['', ''], rows: [['', '']] },
                    }
                "
            >
                <Table2 /> Tabel
            </Button>
            <span class="text-xs text-muted-foreground">
                "1." / "a." / "-" di awal baris jadi daftar; **teks** jadi
                tebal.
            </span>
        </div>
        <Textarea
            ref="area"
            :model-value="model.content"
            rows="5"
            placeholder="Isi bagian ini..."
            :aria-label="`Isi bagian ${index + 1}`"
            @update:model-value="model = { ...model, content: String($event) }"
        />
        <TableEditor
            v-if="model.table"
            :model-value="model.table"
            @update:model-value="model = { ...model, table: $event }"
            @remove="model = { heading: model.heading, content: model.content }"
        />
    </li>
</template>
