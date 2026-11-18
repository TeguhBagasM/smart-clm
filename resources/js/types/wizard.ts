export type DraftTable = { columns: string[]; rows: string[][] };

export type DraftSection = {
    heading: string;
    content: string;
    table?: DraftTable;
};

export type FlowStepType =
    | 'start'
    | 'process'
    | 'decision'
    | 'end'
    | 'document'
    | 'data'
    | 'predefined'
    | 'connector';

export type FlowStep = {
    id: string;
    order: number;
    type: FlowStepType;
    text: string | null;
    /** The lane (actor) the step belongs to. */
    actor?: string;
    color?: string;
    textColor?: string;
    nextId?: string;
    yesId?: string;
    noId?: string;
};

export type DraftFlow = {
    mode: 'none' | 'builder' | 'image';
    style: 'diagram' | 'table';
    lanes: string[];
    steps: FlowStep[];
    has_image: boolean;
};

export type DraftSigner = {
    label: string;
    role: string;
    role_name: string | null;
    /** The id the screens use for the person assigned to sign. */
    assigned: string | null;
    group?: string | null;
};

export type DraftAppendix = { label: string; url: string };

/** Shape of App\Http\Resources\Documents\WizardDraftResource. */
export type Draft = {
    revision: number;
    language_mode: 'single' | 'multi';
    sections: DraftSection[];
    flow: DraftFlow;
    appendix: DraftAppendix[];
    clause_ids: string[];
    signers: DraftSigner[];
    extra_reviewers: DraftSigner[];
    completeness: Record<
        'basic' | 'body' | 'flow' | 'attachments' | 'signers',
        boolean
    >;
    has_file: boolean;
    file_kind: string | null;
    composed: boolean;
};

export type WizardVersion = { id: string; major: number; minor: number };
