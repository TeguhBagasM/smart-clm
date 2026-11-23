<script setup lang="ts">
import { RotateCcw, TriangleAlert } from '@lucide/vue';
import { computed, inject } from 'vue';
import { displayCell } from '@/components/documents/config/header/cellText';
import { computeGeometry } from '@/lib/headerGeometry';
import ColorPicker from '@/components/documents/config/header/color/ColorPicker.vue';
import ContrastHint from '@/components/documents/config/header/color/ContrastHint.vue';
import ContentEditor from '@/components/documents/config/header/ContentEditor.vue';
import { headerContextKey } from '@/components/documents/config/header/headerContext';
import LogoPanel from '@/components/documents/config/header/logo/LogoPanel.vue';
import RowControls from '@/components/documents/config/header/RowControls.vue';
import NumberStepper from '@/components/documents/config/header/NumberStepper.vue';
import type {
    Selection,
    useHeaderEditor,
} from '@/components/documents/config/header/useHeaderEditor';
import CellBorders from '@/components/documents/config/header/CellBorders.vue';
import { ALL_SIDES } from '@/components/documents/config/header/headerBorders';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { HeaderLayout } from '@/types';

const layout = defineModel<HeaderLayout>({ required: true });

const props = defineProps<{
    selection: Selection;
    editor: ReturnType<typeof useHeaderEditor>;
}>();
defineEmits<{ reset: [] }>();

const context = inject(headerContextKey);
const samples = computed(() =>
    Object.fromEntries((context?.fields ?? []).map((f) => [f.value, f.sample])),
);
const cut = computed(() => {
    const s = props.selection;

    if (s.kind !== 'cell' || !props.editor.cell.value) return false;

    const row = layout.value.rows[s.row];
    const box = computeGeometry(layout.value, 483).cells.find(
        (c) => c.rowIndex === s.row && c.cellIndex === s.cell,
    );

    return box
        ? displayCell(props.editor.cell.value, samples.value, box.w, row.height)
              .cut
        : false;
});
</script>

<template>
    <aside
        class="flex flex-col gap-4 rounded-lg border p-4"
        aria-label="Pengaturan elemen terpilih"
    >
        <template v-if="selection.kind === 'cell' && editor.cell.value">
            <h3 class="text-sm font-medium">
                Sel {{ selection.cell + 1 }}, baris {{ selection.row + 1 }}
            </h3>
            <ContentEditor
                :model-value="editor.cell.value.text"
                @update:model-value="editor.patchCell({ text: $event })"
            />
            <NumberStepper
                v-if="layout.rows[selection.row].cells.length > 1"
                :model-value="editor.cell.value.width"
                label="Lebar sel"
                unit="%"
                :min="5"
                :max="95"
                :step="0.5"
                slider
                @update:model-value="editor.setWidth($event)"
            />
            <p
                v-if="cut"
                class="flex items-start gap-1.5 text-xs text-warning-foreground"
                role="status"
            >
                <TriangleAlert class="mt-0.5 size-3.5 shrink-0" />
                Teks lebih panjang dari sel ini dan akan dipotong dengan "…" di
                PDF. Perlebar sel, kecilkan huruf, atau persingkat teks.
            </p>
            <ContrastHint
                v-if="context"
                :text="editor.cell.value.color"
                :background="editor.cell.value.background"
                :palette="context.palette"
            />
            <CellBorders
                :borders="editor.cell.value.borders ?? ALL_SIDES"
                @update="editor.patchCell({ borders: $event })"
            />
            <RowControls
                :row="selection.row"
                :height="layout.rows[selection.row].height"
                :row-count="layout.rows.length"
                :cell-count="layout.rows[selection.row].cells.length"
                @height="editor.setHeight(selection.row, $event)"
                @move="editor.moveRow(selection.row, $event)"
                @add-row="editor.addRowAfter(selection.row)"
                @add-column="editor.addColumn(selection.row)"
            />
        </template>
        <template v-else-if="selection.kind === 'logo'">
            <LogoPanel
                :model-value="layout.logo"
                :row-count="layout.rows.length"
                @update:model-value="layout = { ...layout, logo: $event }"
            />
            <Label
                v-if="layout.logo.enabled"
                class="flex items-center gap-2 border-t pt-4 font-normal"
            >
                <Checkbox
                    :model-value="layout.logo.border !== false"
                    @update:model-value="
                        layout = {
                            ...layout,
                            logo: { ...layout.logo, border: $event === true },
                        }
                    "
                />
                Garis di sekeliling logo
            </Label>
        </template>
        <template v-else>
            <h3 class="text-sm font-medium">Seluruh kop</h3>
            <p class="text-sm text-muted-foreground">
                Klik sel atau logo di pratinjau untuk mengubahnya. Gunakan Tab
                dan tombol panah untuk berpindah sel.
            </p>
            <ColorPicker
                :model-value="layout.border"
                label="Warna garis kop"
                @update:model-value="
                    layout = {
                        ...layout,
                        border:
                            $event ?? context?.palette.border ?? layout.border,
                    }
                "
            />
            <Label class="flex items-center gap-2 font-normal">
                <Checkbox
                    :model-value="layout.logo.enabled"
                    @update:model-value="
                        layout = {
                            ...layout,
                            logo: { ...layout.logo, enabled: $event === true },
                        }
                    "
                />
                Tampilkan logo di kop
            </Label>
            <Label class="flex items-center gap-2 font-normal">
                <Checkbox
                    :model-value="layout.frame !== false"
                    @update:model-value="
                        layout = { ...layout, frame: $event === true }
                    "
                />
                Bingkai di sekeliling seluruh kop
            </Label>
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="self-start"
                @click="$emit('reset')"
                ><RotateCcw /> Kembalikan ke bawaan</Button
            >
        </template>
    </aside>
</template>
