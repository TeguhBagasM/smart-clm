export type ChoiceOption = { value: string; label: string; group?: string };

export type DocTypeRow = {
    id: string;
    code: string;
    name: string;
    description: string | null;
    layout: 'standard' | 'memo' | 'kebijakan';
    header: 'iso' | 'simple';
    letterhead: boolean;
    level: string;
    is_active: boolean;
    sections_count: number;
    columns_count: number;
    clauses_count: number;
    custom_numbering: boolean;
    updated_at: string | null;
};

export type SignatureColumn = {
    label: string;
    /** A role key; empty means anyone may sign. */
    role: string;
    roleName?: string;
};

export type SignatureGroup = { heading?: string; columns: SignatureColumn[] };

export type TypeSection = { heading: string; content: string };

/** What the document type form edits (App\Http\Resources\Dcs\DocTypeResource). */
export type DocTypeForm = {
    id: string;
    code: string;
    name: string;
    description: string | null;
    layoutStyle: 'standard' | 'memo' | 'kebijakan';
    headerStyle: 'iso' | 'simple';
    bodyStyle?: 'standard' | 'formal';
    letterhead: boolean;
    documentLevel: string;
    processOwner: string;
    defaultSections: TypeSection[];
    signatureGroups: SignatureGroup[];
    defaultLanes: string[];
    defaultClauseIds: string[];
    headerLayout?: HeaderLayout;
    is_active: boolean;
};

export type ClauseRow = {
    id: string;
    title: string;
    excerpt: string;
    category: string;
    is_mandatory: boolean;
    tags: string[];
    types: string[];
    is_active: boolean;
    updated_at: string | null;
};

/** What the clause form edits (App\Http\Resources\Dcs\ClauseResource). */
export type ClauseForm = {
    id: string;
    title: string;
    content: string;
    category: string;
    isMandatory: boolean;
    tags: string[];
    applicableDocTypeCodes: string[];
};

export type DepartmentRow = {
    id: string;
    code: string;
    name: string;
    is_active: boolean;
    documents_count: number;
};

export type NumberingRow = {
    id: string;
    code: string;
    name: string;
    is_active: boolean;
    mask: string;
    custom: boolean;
    example: string;
};

/** Schema version 2 of App\Support\Dcs\Header\HeaderLayout: colours are hex, a cell is one text with {field} placeholders. */
export type HeaderCell = {
    width: number;
    text: string;
    bold: boolean;
    italic: boolean;
    uppercase: boolean;
    align: 'left' | 'center' | 'right';
    fontSize: number | null;
    background: string | null;
    color: string;
};

export type HeaderRow = { height: number; cells: HeaderCell[] };

export type HeaderLogo = {
    enabled: boolean;
    source: 'company' | 'custom';
    assetId: string | null;
    side: 'left' | 'right';
    rows: number;
    width: number;
    valign: 'top' | 'middle' | 'bottom';
    padding: number;
};

export type HeaderLayout = {
    version: number;
    border: string;
    logo: HeaderLogo;
    rows: HeaderRow[];
};

/** App\Support\Dcs\Header\HeaderPalette::forEditor(). */
export type HeaderPalette = {
    swatches: string[];
    border: string;
    text: string;
    light: string;
    dark: string;
};

export type HeaderPreset = {
    key: string;
    name: string;
    description: string;
    layout: HeaderLayout;
};

export type BrandColor = { hex: string; name: string };

export type HeaderFieldOption = {
    value: string;
    label: string;
    sample: string;
};
