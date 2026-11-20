import type {
    LibraryDraftClause,
    LibraryDraftField,
    LibraryDraftParty,
} from '@/types';

/**
 * Pure helpers of the library screens. The server checks everything again; these
 * only help the person see what they are writing.
 */
const PLACEHOLDER = /\{\{([A-Za-z][A-Za-z0-9_]{0,59})\}\}/g;

/** The field codes written in a text, once each, in order of first use. */
export function placeholderCodes(text: string | null | undefined): string[] {
    const codes: string[] = [];

    for (const match of (text ?? '').matchAll(PLACEHOLDER)) {
        if (!codes.includes(match[1])) {
            codes.push(match[1]);
        }
    }

    return codes;
}

/** Puts an item at a new place in a copy of the list. */
export function moveTo<T>(items: T[], from: number, to: number): T[] {
    if (from === to || from < 0 || to < 0 || to >= items.length) {
        return [...items];
    }

    const next = [...items];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);

    return next;
}

/** The text of a party or a clause without the blanks the person left around it. */
export function tidy(text: string): string {
    return text.trim().replace(/\n{3,}/g, '\n\n');
}

export type DraftState = {
    opening: string;
    closing: string;
    addendum: string;
    parties: string[];
    clauses: {
        libraryId: number;
        title: string;
        body: string;
        sourceUpdated: boolean;
    }[];
    fields: { id: number; required: boolean }[];
};

/** The state of the draft as the editor starts it, from the saved version. */
export function stateFromVersion(version: {
    opening_paragraph: string | null;
    closing_paragraph: string | null;
    addendum_recital: string | null;
    parties: LibraryDraftParty[];
    clauses: LibraryDraftClause[];
    variables: LibraryDraftField[];
}): DraftState {
    return {
        opening: version.opening_paragraph ?? '',
        closing: version.closing_paragraph ?? '',
        addendum: version.addendum_recital ?? '',
        parties: version.parties.map((party) => party.label),
        clauses: version.clauses
            .filter((clause) => clause.source_clause_id !== null)
            .map((clause) => ({
                libraryId: clause.source_clause_id as number,
                title: clause.title,
                body: clause.body,
                sourceUpdated: clause.source_updated,
            })),
        fields: version.variables.map((field) => ({
            id: field.id,
            required: field.is_required,
        })),
    };
}

/** The body the draft endpoint expects. */
export function draftPayload(state: DraftState, expectedRevision: number) {
    return {
        expected_revision: expectedRevision,
        opening_paragraph: tidy(state.opening),
        closing_paragraph: tidy(state.closing),
        addendum_recital: tidy(state.addendum),
        parties: state.parties.map(tidy).filter((label) => label !== ''),
        clause_ids: state.clauses.map((clause) => clause.libraryId),
        variables: state.fields.map((field) => ({
            id: field.id,
            required: field.required,
        })),
    };
}

/** Field codes the draft uses in its texts but has not chosen. */
export function missingFields(
    state: DraftState,
    fieldCodes: Map<number, string>,
    systemTokens: string[] = [],
): string[] {
    const chosen = new Set(
        state.fields.map((field) => fieldCodes.get(field.id)),
    );
    const texts = [
        state.opening,
        state.closing,
        state.addendum,
        ...state.clauses.map((clause) => clause.body),
    ];
    const missing: string[] = [];

    for (const text of texts) {
        for (const code of placeholderCodes(text)) {
            if (
                !chosen.has(code) &&
                !systemTokens.includes(code) &&
                !missing.includes(code)
            ) {
                missing.push(code);
            }
        }
    }

    return missing;
}

/** A date as Indonesian text, e.g. "5 Mei 2026". */
export function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/** A field code written the way a text holds it, e.g. "{{Nama}}". */
export function placeholderText(code: string): string {
    return '{{' + code + '}}';
}
