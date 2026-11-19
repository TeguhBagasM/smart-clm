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

export type FlowSide = 'top' | 'right' | 'bottom' | 'left';

/** Sides a line leaves its step and enters the next one; absent = chosen automatically. */
export type FlowPorts = {
    exit?: FlowSide;
    entry?: FlowSide;
    /** Bend points set by hand, in grid units (x = lane + fraction, y = row + fraction). */
    points?: { x: number; y: number }[];
};

/** A step's size as a factor of the default box (1 = default). */
export type FlowStepSize = { w: number; h: number };

export type FlowStep = {
    id: string;
    order: number;
    type: FlowStepType;
    text: string | null;
    /** The lane (actor) the step belongs to. */
    actor?: string;
    /** Grid row on the canvas (0 = first); absent on drafts made before the canvas. */
    row?: number;
    /** Line sides chosen by the author, per line (next, Ya, Tidak). */
    ports?: Partial<Record<'next' | 'yes' | 'no', FlowPorts>>;
    /** Resized by the author; absent = default size. */
    size?: FlowStepSize;
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
