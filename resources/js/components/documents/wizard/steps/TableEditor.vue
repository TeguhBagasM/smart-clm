<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { removeItem } from '@/lib/arrays';
import type { DraftTable } from '@/types';

const model = defineModel<DraftTable>({ required: true });

defineEmits<{ remove: [] }>();

function setHeader(column: number, value: string): void {
    model.value = {
        ...model.value,
        columns: model.value.columns.map((name, index) =>
            index === column ? value : name,
        ),
    };
}

function setCell(row: number, column: number, value: string): void {
    model.value = {
        ...model.value,
        rows: model.value.rows.map((cells, index) =>
            index === row
                ? cells.map((cell, position) =>
                      position === column ? value : cell,
                  )
                : cells,
        ),
    };
}

function addColumn(): void {
    model.value = {
        columns: [...model.value.columns, ''],
        rows: model.value.rows.map((cells) => [...cells, '']),
    };
}

function removeColumn(column: number): void {
    model.value = {
        columns: removeItem(model.value.columns, column),
        rows: model.value.rows.map((cells) => removeItem(cells, column)),
    };
}
</script>

<template>
    <div class="flex flex-col gap-2 rounded-md border p-3">
        <div class="overflow-x-auto">
            <table
                class="w-full min-w-max border-separate border-spacing-1 text-sm"
            >
                <thead>
                    <tr>
                        <th
                            v-for="(name, column) in model.columns"
                            :key="column"
                            class="min-w-32 font-normal"
                        >
                            <div class="flex gap-1">
                                <Input
                                    :model-value="name"
                                    placeholder="Kolom"
                                    :aria-label="`Judul kolom ${column + 1}`"
                                    class="font-medium"
                                    @update:model-value="
                                        setHeader(column, String($event))
                                    "
                                />
                                <Button
                                    v-if="model.columns.length > 1"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Hapus kolom ${column + 1}`"
                                    @click="removeColumn(column)"
                                >
                                    <Trash2 class="size-4 text-destructive" />
                                </Button>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(cells, row) in model.rows" :key="row">
                        <td v-for="(cell, column) in cells" :key="column">
                            <Input
                                :model-value="cell"
                                :aria-label="`Baris ${row + 1} kolom ${column + 1}`"
                                @update:model-value="
                                    setCell(row, column, String($event))
                                "
                            />
                        </td>
                        <td>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`Hapus baris ${row + 1}`"
                                @click="
                                    model = {
                                        ...model,
                                        rows: removeItem(model.rows, row),
                                    }
                                "
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="
                    model = {
                        ...model,
                        rows: [...model.rows, model.columns.map(() => '')],
                    }
                "
            >
                <Plus /> Baris
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="addColumn"
            >
                <Plus /> Kolom
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="$emit('remove')"
            >
                Hapus tabel
            </Button>
        </div>
    </div>
</template>
