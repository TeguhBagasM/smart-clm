<script setup lang="ts">
import { provide, ref } from 'vue';
import { headerContextKey } from '@/components/documents/config/header/headerContext';
import FormatBar from '@/components/documents/config/header/FormatBar.vue';
import HeaderCanvas from '@/components/documents/config/header/HeaderCanvas.vue';
import HeaderSidePanel from '@/components/documents/config/header/HeaderSidePanel.vue';
import type { Zoom } from '@/components/documents/config/header/ZoomControl.vue';
import { useHeaderHistory } from '@/components/documents/config/header/useHeaderHistory';
import { useHeaderEditor } from '@/components/documents/config/header/useHeaderEditor';
import Panel from '@/components/documents/Panel.vue';
import type {
    BrandColor,
    HeaderFieldOption,
    HeaderLayout,
    HeaderPalette,
    HeaderPreset,
} from '@/types';

const model = defineModel<HeaderLayout>({ required: true });

const props = defineProps<{
    fields: HeaderFieldOption[];
    defaultLayout: HeaderLayout;
    presets: HeaderPreset[];
    palette: HeaderPalette;
    brandColors: BrandColor[];
    hasCompanyLogo: boolean;
}>();

provide(headerContextKey, {
    fields: props.fields,
    palette: props.palette,
    brandColors: ref(props.brandColors),
    hasCompanyLogo: props.hasCompanyLogo,
});

const editor = useHeaderEditor(model);
const history = useHeaderHistory(model);
const zoom = ref<Zoom>('fit');

/** Replaces the whole letterhead with a copy of a preset (the shipped default for "reset"). */
function applyLayout(layout: HeaderLayout): void {
    model.value = JSON.parse(JSON.stringify(layout));
    editor.select({ kind: 'header' });
}

const isTyping = (target: EventTarget | null): boolean =>
    target instanceof HTMLElement &&
    target.closest('input, textarea, select, [contenteditable="true"]') !==
        null;

/** Ctrl/Cmd+Z and Shift+Z (or Ctrl+Y) outside text fields; Delete removes the focused cell; Esc leaves the selection. */
function onKeydown(event: KeyboardEvent): void {
    const key = event.key.toLowerCase();

    if (
        (event.metaKey || event.ctrlKey) &&
        (key === 'z' || key === 'y') &&
        !isTyping(event.target)
    ) {
        event.preventDefault();
        if (key === 'y' || event.shiftKey) history.redo();
        else history.undo();

        return;
    }

    const cell = (event.target as Element).closest?.('[data-cell]');

    if (!cell) return;

    if (event.key === 'Escape') editor.select({ kind: 'header' });
    else if (event.key === 'Delete' || event.key === 'Backspace') {
        const [row, index] = (cell.getAttribute('data-cell') ?? '')
            .split('-')
            .map(Number);

        event.preventDefault();
        editor.select({ kind: 'cell', row, cell: index });
        editor.deleteCell();
    }
}
</script>

<template>
    <Panel title="Kop surat">
        <div class="@container flex flex-col gap-3" @keydown="onKeydown">
            <p class="text-sm text-muted-foreground">
                Ubah kop langsung di pratinjau: klik sel atau logo, lalu atur
                lewat toolbar dan panel pengaturan. Nomor revisi, tanggal
                terbit, status, dan halaman diisi otomatis oleh sistem.
            </p>
            <FormatBar
                v-model:zoom="zoom"
                :cell="editor.cell.value"
                :can-delete="editor.canDelete.value"
                :can-undo="history.canUndo.value"
                :can-redo="history.canRedo.value"
                :presets="presets"
                @patch="editor.patchCell"
                @remove="editor.deleteCell"
                @undo="history.undo"
                @redo="history.redo"
                @preset="applyLayout($event.layout)"
            />
            <div
                class="grid items-start gap-4 @[60rem]:grid-cols-[minmax(0,1fr)_22rem]"
            >
                <HeaderCanvas
                    v-model:layout="model"
                    :selection="editor.selection.value"
                    :zoom="zoom"
                    @select="editor.select"
                    @add-row="editor.addRowAfter"
                    @add-column="editor.addColumn"
                />
                <HeaderSidePanel
                    v-model="model"
                    :selection="editor.selection.value"
                    :editor="editor"
                    @reset="applyLayout(defaultLayout)"
                />
            </div>
        </div>
    </Panel>
</template>
