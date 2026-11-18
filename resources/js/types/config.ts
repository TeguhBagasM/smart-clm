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

export type HeaderCell = {
    span: number;
    label: string;
    field: string;
    bold: boolean;
    italic: boolean;
    uppercase: boolean;
    align: 'left' | 'center' | 'right';
    fill: 'none' | 'muted' | 'dark';
};

export type HeaderRow = { height: number; cells: HeaderCell[] };

/** App\Support\Dcs\Header\HeaderLayout. */
export type HeaderLayout = {
    logo: { enabled: boolean; rows: number; width: number };
    rows: HeaderRow[];
};

export type HeaderFieldOption = {
    value: string;
    label: string;
    sample: string;
};
