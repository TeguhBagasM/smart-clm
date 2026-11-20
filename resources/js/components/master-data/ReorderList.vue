<script setup lang="ts">
import { ArrowDown, ArrowUp, GripVertical } from '@lucide/vue';
import { ref } from 'vue';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { OrderRow } from '@/types';

/**
 * Sets the order: drag a row by its handle, or use the up/down buttons (the
 * way on a keyboard or a phone). The parent keeps the order and saves the whole list.
 */
defineProps<{
    rows: OrderRow[];
    saving: boolean;
    dirty: boolean;
    error: string | null;
}>();

const emit = defineEmits<{
    move: [from: number, to: number];
    save: [];
    cancel: [];
}>();
const dragging = ref<number | null>(null);

function drop(to: number): void {
    if (dragging.value !== null) {
        emit('move', dragging.value, to);
    }

    dragging.value = null;
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <Alert v-if="error" variant="destructive"
            ><p class="text-sm">{{ error }}</p></Alert
        >
        <ol class="flex flex-col rounded-xl border" aria-label="Urutan pilihan">
            <li
                v-for="(row, index) in rows"
                :key="row.id"
                class="flex items-center gap-2 border-b p-2 last:border-b-0"
                :class="dragging === index && 'bg-muted'"
                draggable="true"
                @dragstart="dragging = index"
                @dragover.prevent
                @drop.prevent="drop(index)"
                @dragend="dragging = null"
            >
                <GripVertical
                    class="hidden size-5 shrink-0 cursor-grab text-muted-foreground md:block"
                    aria-hidden="true"
                />
                <span
                    class="w-6 shrink-0 text-right text-sm text-muted-foreground"
                    >{{ index + 1 }}</span
                >
                <span class="min-w-0 flex-1 truncate font-medium">{{
                    row.name
                }}</span>
                <Badge
                    v-if="!row.is_active"
                    variant="outline"
                    class="text-muted-foreground"
                    >Disembunyikan</Badge
                >
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-11 md:size-9"
                    :disabled="index === 0 || saving"
                    :aria-label="`Naikkan ${row.name}`"
                    @click="emit('move', index, index - 1)"
                >
                    <ArrowUp />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-11 md:size-9"
                    :disabled="index === rows.length - 1 || saving"
                    :aria-label="`Turunkan ${row.name}`"
                    @click="emit('move', index, index + 1)"
                >
                    <ArrowDown />
                </Button>
            </li>
        </ol>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button
                variant="secondary"
                class="h-11 sm:h-9"
                :disabled="saving"
                @click="emit('cancel')"
                >Batal</Button
            >
            <Button
                class="h-11 sm:h-9"
                :disabled="!dirty || saving"
                @click="emit('save')"
            >
                {{ saving ? 'Menyimpan…' : 'Simpan urutan' }}
            </Button>
        </div>
    </div>
</template>
