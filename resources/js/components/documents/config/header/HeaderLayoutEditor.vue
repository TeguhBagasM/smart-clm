<script setup lang="ts">
import { Plus, RotateCcw } from '@lucide/vue';
import HeaderPreview from '@/components/documents/config/header/HeaderPreview.vue';
import HeaderRowEditor from '@/components/documents/config/header/HeaderRowEditor.vue';
import Panel from '@/components/documents/Panel.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { moveItem, removeItem } from '@/lib/arrays';
import type { HeaderFieldOption, HeaderLayout, HeaderRow } from '@/types';

const model = defineModel<HeaderLayout>({ required: true });

const props = defineProps<{
    fields: HeaderFieldOption[];
    defaultLayout: HeaderLayout;
}>();

const MAX_ROWS = 12;

function setRows(rows: HeaderRow[]): void {
    model.value = { ...model.value, rows };
}

/** A copy, so editing never touches the shipped default. */
function reset(): void {
    model.value = JSON.parse(JSON.stringify(props.defaultLayout));
}

function addRow(): void {
    setRows([
        ...model.value.rows,
        {
            height: 18,
            cells: [
                {
                    span: 12,
                    label: '',
                    field: 'text',
                    bold: false,
                    italic: false,
                    uppercase: false,
                    align: 'left',
                    fill: 'none',
                },
            ],
        },
    ]);
}
</script>

<template>
    <Panel title="Kop surat">
        <p class="mb-3 text-sm text-muted-foreground">
            Atur baris dan kolom kop yang tercetak di setiap halaman. Nomor
            revisi, tanggal terbit, status, dan halaman diisi otomatis oleh
            sistem.
        </p>
        <div class="sticky top-0 z-10 mb-4 bg-card pb-2">
            <HeaderPreview :layout="model" :fields="fields" />
        </div>

        <div class="mb-4 flex flex-wrap items-end gap-4">
            <Label class="flex items-center gap-2 font-normal">
                <Checkbox
                    :model-value="model.logo.enabled"
                    @update:model-value="
                        model = {
                            ...model,
                            logo: { ...model.logo, enabled: $event === true },
                        }
                    "
                />
                Tampilkan logo perusahaan
            </Label>
            <template v-if="model.logo.enabled">
                <Label class="flex items-center gap-2 font-normal">
                    Di samping
                    <Input
                        type="number"
                        min="1"
                        :max="model.rows.length"
                        class="h-8 w-16"
                        :model-value="model.logo.rows"
                        @update:model-value="
                            model = {
                                ...model,
                                logo: {
                                    ...model.logo,
                                    rows: Math.min(
                                        model.rows.length,
                                        Math.max(1, Number($event) || 1),
                                    ),
                                },
                            }
                        "
                    />
                    baris pertama
                </Label>
                <Label class="flex items-center gap-2 font-normal">
                    Lebar logo
                    <Input
                        type="number"
                        min="10"
                        max="40"
                        class="h-8 w-16"
                        :model-value="model.logo.width"
                        @update:model-value="
                            model = {
                                ...model,
                                logo: {
                                    ...model.logo,
                                    width: Math.min(
                                        40,
                                        Math.max(10, Number($event) || 22),
                                    ),
                                },
                            }
                        "
                    />
                    %
                </Label>
            </template>
        </div>

        <ol class="flex flex-col gap-3">
            <HeaderRowEditor
                v-for="(row, index) in model.rows"
                :key="index"
                :model-value="row"
                :fields="fields"
                :index="index"
                :first="index === 0"
                :last="index === model.rows.length - 1"
                :removable="model.rows.length > 1"
                :beside="model.logo.enabled && index < model.logo.rows"
                @update:model-value="
                    setRows(
                        model.rows.map((r, p) => (p === index ? $event : r)),
                    )
                "
                @move="setRows(moveItem(model.rows, index, $event))"
                @remove="setRows(removeItem(model.rows, index))"
            />
        </ol>
        <div class="mt-3 flex flex-wrap gap-2">
            <Button
                v-if="model.rows.length < MAX_ROWS"
                type="button"
                variant="outline"
                @click="addRow"
            >
                <Plus /> Tambah baris
            </Button>
            <Button type="button" variant="ghost" @click="reset">
                <RotateCcw /> Kembalikan ke bawaan
            </Button>
        </div>
    </Panel>
</template>
