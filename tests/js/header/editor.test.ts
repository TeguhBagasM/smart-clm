import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import {
    parseContent,
    serializeContent,
} from '@/components/documents/config/header/contentTokens';
import { useHeaderEditor } from '@/components/documents/config/header/useHeaderEditor';
import type { HeaderLayout } from '@/types';

const cell = (width: number, text: string) => ({
    width,
    text,
    bold: false,
    italic: false,
    uppercase: false,
    align: 'left' as const,
    fontSize: null,
    background: null,
    color: 'auto',
});
const layout = (): HeaderLayout => ({
    version: 2,
    border: '#404552',
    logo: {
        enabled: true,
        source: 'company',
        assetId: null,
        side: 'left',
        rows: 1,
        width: 22,
        valign: 'middle',
        padding: 8,
    },
    rows: [
        { height: 20, cells: [cell(100, 'Judul')] },
        {
            height: 18,
            cells: [cell(60, 'No : {documentNumber}'), cell(40, 'Hal {page}')],
        },
    ],
});

describe('cell content', () => {
    it('round-trips text and field chips and drops stray braces', () => {
        const segments = parseContent('No : {documentNumber}{page}');

        expect(segments.map((s) => s.kind)).toEqual([
            'text',
            'field',
            'text',
            'field',
            'text',
        ]);
        expect(serializeContent(segments)).toBe('No : {documentNumber}{page}');
        expect(serializeContent([{ kind: 'text', value: 'a{b}c' }])).toBe(
            'abc',
        );
    });
});

describe('editor state', () => {
    it('edits the selected cell, keeps widths at 100 and falls back when the selection disappears', () => {
        const model = ref(layout());
        const editor = useHeaderEditor(model);

        editor.select({ kind: 'cell', row: 1, cell: 0 });
        editor.patchCell({ bold: true, text: 'Baru' });
        editor.setWidth(70);

        expect(model.value.rows[1].cells.map((c) => c.width)).toEqual([70, 30]);
        expect(model.value.rows[1].cells[0]).toMatchObject({
            bold: true,
            text: 'Baru',
        });

        editor.deleteCell();
        expect(model.value.rows[1].cells).toHaveLength(1);
        expect(model.value.rows[1].cells[0].width).toBe(100);

        editor.select({ kind: 'cell', row: 1, cell: 0 });
        editor.deleteCell();
        expect(model.value.rows).toHaveLength(1);
        expect(editor.selection.value).toEqual({ kind: 'header' });
    });

    it('adds a row below with sensible content and selects it, and refuses to delete the last cell', () => {
        const model = ref(layout());
        const editor = useHeaderEditor(model);

        editor.addRowAfter(0);

        expect(model.value.rows).toHaveLength(3);
        expect(model.value.rows[1].cells[0].text).not.toBe('');
        expect(editor.selection.value).toEqual({
            kind: 'cell',
            row: 1,
            cell: 0,
        });

        const single = ref({ ...layout(), rows: [layout().rows[0]] });
        const e2 = useHeaderEditor(single);
        e2.select({ kind: 'cell', row: 0, cell: 0 });

        expect(e2.canDelete.value).toBe(false);
    });
});

describe('logo selection', () => {
    it('stays selectable after the logo is switched off, so it can be switched on again', () => {
        const model = ref(layout());
        const editor = useHeaderEditor(model);

        editor.select({ kind: 'logo' });
        model.value = {
            ...model.value,
            logo: { ...model.value.logo, enabled: false },
        };

        expect(editor.selection.value).toEqual({ kind: 'logo' });
    });
});
