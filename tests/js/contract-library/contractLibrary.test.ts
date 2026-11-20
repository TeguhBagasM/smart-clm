import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    draftPayload,
    formatDate,
    missingFields,
    moveTo,
    placeholderCodes,
    placeholderText,
    stateFromVersion,
} from '@/lib/contractLibrary';
import { libraryRead, libraryRequest } from '@/lib/libraryRequest';

describe('placeholders in a text', () => {
    it('reads each valid field once, in order of first use', () => {
        expect(
            placeholderCodes('A {{Nama}} b {{Mata_Uang1}} c {{Nama}}'),
        ).toEqual(['Nama', 'Mata_Uang1']);
    });

    it('ignores braces that are not a field, such as {1} or {{ Nama }}', () => {
        expect(
            placeholderCodes('Harga {1} dan {{ Nama }} dan {{a|upper}}'),
        ).toEqual([]);
    });

    it('writes a field back as {{Code}}', () => {
        expect(placeholderText('NamaPerusahaan')).toBe('{{NamaPerusahaan}}');
    });
});

describe('moving items', () => {
    it('puts the item at its new place in a copy, and leaves the original list alone', () => {
        const list = ['a', 'b', 'c'];
        expect(moveTo(list, 0, 2)).toEqual(['b', 'c', 'a']);
        expect(list).toEqual(['a', 'b', 'c']);
    });

    it('changes nothing for a place outside the list', () => {
        expect(moveTo(['a', 'b'], 0, 5)).toEqual(['a', 'b']);
    });
});

const version = {
    opening_paragraph: 'Pada {{Tanggal}}.',
    closing_paragraph: null,
    addendum_recital: null,
    parties: [{ id: 1, position: 1, label: 'Pihak Pertama' }],
    clauses: [
        {
            id: 5,
            position: 1,
            title: 'Bayar',
            body: 'Bayar {{Nilai}}.',
            is_mandatory: true,
            is_protected: false,
            source_clause_id: 9,
            source_revision: 2,
            source_updated: true,
        },
    ],
    variables: [
        { id: 3, code: 'Nilai', name: 'Nilai', is_required: true, position: 1 },
    ],
};

describe('the draft state', () => {
    it('starts from the saved version, with the library clause it came from', () => {
        const state = stateFromVersion(version as never);

        expect(state.clauses).toEqual([
            {
                libraryId: 9,
                title: 'Bayar',
                body: 'Bayar {{Nilai}}.',
                sourceUpdated: true,
            },
        ]);
        expect(state.fields).toEqual([{ id: 3, required: true }]);
        expect(state.parties).toEqual(['Pihak Pertama']);
    });

    it('sends empty lists as lists, so the server sees them as present', () => {
        const payload = draftPayload(
            {
                opening: '',
                closing: '',
                addendum: '',
                parties: [],
                clauses: [],
                fields: [],
            },
            4,
        );

        expect(payload).toEqual({
            expected_revision: 4,
            opening_paragraph: '',
            closing_paragraph: '',
            addendum_recital: '',
            parties: [],
            clause_ids: [],
            variables: [],
        });
    });

    it('drops blank parties and trims the texts', () => {
        const payload = draftPayload(
            {
                opening: '  Halo  ',
                closing: '',
                addendum: '',
                parties: [' Pihak A ', '  '],
                clauses: [],
                fields: [],
            },
            1,
        );

        expect(payload.opening_paragraph).toBe('Halo');
        expect(payload.parties).toEqual(['Pihak A']);
    });

    it('names the fields a clause uses that the draft has not chosen', () => {
        const state = stateFromVersion(version as never);
        state.fields = [];
        const codes = new Map([[3, 'Nilai']]);

        expect(missingFields(state, codes)).toEqual(['Tanggal', 'Nilai']);
    });

    it('does not ask for a system token, which the contract fills itself', () => {
        const state = stateFromVersion({
            ...version,
            opening_paragraph: 'Antara {{Party1Name}}.',
            clauses: [],
        } as never);
        state.fields = [];

        expect(missingFields(state, new Map(), ['Party1Name'])).toEqual([]);
    });
});

describe('the request helper', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('maps the first error of each field, and keeps the message of the server', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(
                async () =>
                    new Response(
                        JSON.stringify({
                            message: 'Tidak valid.',
                            errors: { clause_ids: ['Pasal tidak ada.'] },
                        }),
                        {
                            status: 422,
                            headers: { 'Content-Type': 'application/json' },
                        },
                    ),
            ),
        );

        const result = await libraryRequest('PUT', '/x', { a: 1 }, 'gagal');

        expect(result).toEqual({
            ok: false,
            errors: { clause_ids: 'Pasal tidak ada.' },
            message: 'Tidak valid.',
        });
    });

    it('says plainly when there are too many requests', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(
                async () =>
                    new Response('{}', {
                        status: 429,
                        headers: { 'Content-Type': 'application/json' },
                    }),
            ),
        );

        const result = await libraryRequest('POST', '/x', {}, 'gagal');

        expect(result.ok).toBe(false);
        expect(result.ok === false && result.message).toContain(
            'Terlalu banyak permintaan',
        );
    });

    it('falls back to the given text when the connection fails, and keeps no body on a read', async () => {
        const fetchMock = vi.fn(
            async (_url: string, _init?: RequestInit): Promise<Response> => {
                throw new TypeError('network');
            },
        );
        vi.stubGlobal('fetch', fetchMock);

        const result = await libraryRead('/preview', 'Koneksi terputus.');

        expect(result).toEqual({
            ok: false,
            errors: {},
            message: 'Koneksi terputus.',
        });
        expect(fetchMock.mock.calls[0][1]).not.toHaveProperty('body');
    });

    it('formats a date in Indonesian', () => {
        expect(formatDate('2026-05-05T00:00:00Z')).toMatch(/Mei 2026/);
    });
});
