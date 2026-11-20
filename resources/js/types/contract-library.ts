/**
 * Shapes of what the contract library controllers send (App\Http\Resources\Templates
 * and App\Support\Templates\LibraryOptions). Texts come from config/contract_templates.php.
 */
export type LibraryCan = Record<string, boolean>;

export type LibraryClauseRow = {
    id: number;
    title: string;
    body: string;
    category: { id: number; name: string } | null;
    is_mandatory: boolean;
    is_protected: boolean;
    revision: number;
    is_active: boolean;
    usage_count: number;
    can: { update: boolean; deactivate: boolean };
};

export type LibraryFieldRow = {
    id: number;
    code: string;
    name: string;
    value_type: { value: string; label: string };
    description: string | null;
    default_value: string | null;
    source_field: string | null;
    is_active: boolean;
    usage_count: number;
    can: { update: boolean; deactivate: boolean };
};

export type LibraryTemplateRow = {
    id: number;
    name: string;
    description: string | null;
    module: { value: string; label: string };
    category: { id: number; name: string } | null;
    is_active: boolean;
    published_version: { id: number; version_no: number } | null;
    draft: { id: number; version_no: number; draft_revision: number } | null;
    can: LibraryCan;
};

export type LibraryDraftClause = {
    id: number;
    position: number;
    title: string;
    body: string;
    is_mandatory: boolean;
    is_protected: boolean;
    source_clause_id: number | null;
    source_revision: number | null;
    source_updated: boolean;
};

export type LibraryDraftParty = { id: number; position: number; label: string };

export type LibraryDraftField = {
    id: number;
    code: string;
    name: string;
    is_required: boolean;
    position: number;
};

export type LibraryVersion = {
    id: number;
    version_no: number;
    status: { value: 'draft' | 'published' | 'retired'; label: string };
    opening_paragraph: string | null;
    closing_paragraph: string | null;
    addendum_recital: string | null;
    draft_revision: number;
    published_at: string | null;
    clauses: LibraryDraftClause[];
    parties: LibraryDraftParty[];
    variables: LibraryDraftField[];
};

export type LibraryVersionSummary = {
    id: number;
    version_no: number;
    status: { value: string; label: string };
    published_at: string | null;
};

export type LibraryClauseOption = {
    value: number;
    label: string;
    revision: number;
    category: string | null;
    body: string;
};

export type LibraryFieldOption = {
    value: number;
    label: string;
    code: string;
    type: string;
};

export type LibraryImpactTexts = {
    impact_hide_title: string;
    impact_used: string;
    impact_unused: string;
    impact_show_title: string;
    impact_show: string;
};

export type LibraryPreviewSegment =
    | { text: string }
    | { code: string; value: string; missing: boolean };

export type LibraryPreview = {
    version_no: number;
    opening: LibraryPreviewSegment[] | null;
    parties: string[];
    clauses: {
        title: string;
        mandatory: boolean;
        segments: LibraryPreviewSegment[];
    }[];
    closing: LibraryPreviewSegment[] | null;
    addendum: LibraryPreviewSegment[] | null;
    fields: { code: string; name: string; required: boolean; sample: string }[];
    missing: string[];
};

export type LibraryAiDraft = {
    title: string;
    body: string;
    reasoning: string | null;
    warnings: string[];
};

/** What a list page is given around its table (from LibraryOptions::pageInfo). */
export type LibraryAssistTexts = {
    title: string;
    description_label: string;
    description_help: string;
    button: string;
    loading: string;
    warnings: string;
    applied: string;
};

export type LibraryPageInfo = {
    page: 'clauses' | 'fields' | 'templates';
    title: string;
    description: string;
    add: string;
    empty: string;
    empty_search: string;
    search: string;
    columns: { key: string; label: string }[];
    texts: LibraryImpactTexts & { save_failed: string };
    usage_notice: string | null;
    system_tokens: string[];
    hints: { explain: string; unknown: string; ok: string };
    assist: LibraryAssistTexts;
    sources?: { value: string; label: string }[];
    types?: { value: string; label: string }[];
    editor?: LibraryEditorTexts;
};

/** Texts of the template editor page (config contract_templates.editor). */
export type LibraryEditorTexts = {
    title: string;
    description: string;
    save: string;
    publish: string;
    start_draft: string;
    duplicate: string;
    back: string;
    tabs: { draft: string; preview: string; history: string };
    tabs_label: string;
    saved: string;
    save_failed: string;
    no_clauses: string;
    publish_title: string;
    publish_message: string;
    publish_first: string;
    publish_confirm: string;
    unsaved: string;
    saved_now: string;
    published_readonly: string;
    preview_note: string;
    preview_refresh: string;
    preview_empty: string;
    history_title: string;
    history_empty: string;
    history_published: string;
    labels: Record<string, string>;
};
