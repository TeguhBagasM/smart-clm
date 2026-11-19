export type VersionStatus =
    | 'draft'
    | 'under_review'
    | 'approved'
    | 'effective'
    | 'superseded';

/** Shape of the versions in App\Http\Resources\Documents\DocumentListResource. */
export type VersionSummary = {
    id: string;
    major: number;
    minor: number;
    status: VersionStatus;
    status_label: string;
    effective_at: string | null;
    review_due_at: string | null;
};

/** Shape of App\Http\Resources\Documents\DocumentListResource. */
export type DocumentRow = {
    id: string;
    number: string;
    title: string;
    type: { code: string; name: string };
    department: string;
    owner: string;
    latest_version: VersionSummary | null;
    current_version: VersionSummary | null;
    has_file: boolean;
    open_comments: number;
    review_overdue: boolean;
    updated_at: string | null;
};

/** Shape of the versions in App\Http\Resources\Documents\DocumentDetailResource. */
export type VersionRow = {
    id: string;
    major: number;
    minor: number;
    status: VersionStatus;
    status_label: string;
    effective_at: string | null;
    superseded_at: string | null;
    change_summary: string | null;
    has_file: boolean;
    is_current: boolean;
    is_latest: boolean;
};

/** Shape of App\Http\Resources\Documents\DocumentDetailResource. */
export type DocumentDetail = {
    id: string;
    number: string;
    title: string;
    type: { code: string; name: string };
    department: string;
    owner: string;
    archive_category: string | null;
    created_at: string | null;
    updated_at: string | null;
    versions: VersionRow[];
};

export type RosterEntry = {
    order: number;
    label: string;
    role: string | null;
    assigned_to: string | null;
    status: 'pending' | 'approved';
    signed_by: string | null;
    signed_at: string | null;
    is_current: boolean;
};

export type CommentAnchor = {
    field: 'purpose' | 'scope' | 'section' | 'flow' | 'general';
    sectionHeading?: string;
    quote?: string;
};

export type ReviewComment = {
    id: string;
    author: string;
    author_role: string | null;
    external: boolean;
    anchor: CommentAnchor;
    body: string;
    status: 'open' | 'resolved';
    round: number;
    resolved_by: string | null;
    resolved_at: string | null;
    created_at: string | null;
};

export type ReceiptSummary = {
    counts: Record<'pending' | 'read' | 'acknowledged', number>;
    recipients: {
        name: string;
        status: 'pending' | 'read' | 'acknowledged';
        read_at: string | null;
        acknowledged_at: string | null;
    }[];
};

/** What the signed-in person may do to the version now (DocumentVersionPolicy). */
export type VersionAbilities = {
    edit: boolean;
    submit: boolean;
    sign: boolean;
    request_changes: boolean;
    return_to_draft: boolean;
    comment: boolean;
    resolve_comment: boolean;
    release: boolean;
    cancel_release: boolean;
    withdraw: boolean;
    distribute: boolean;
    reassign_signer: boolean;
    acknowledge: boolean;
};

export type RecipientOption = { id: number; name: string; role: string };

/** Shape of App\Http\Resources\Documents\VersionDetailResource. */
export type VersionDetail = {
    id: string;
    major: number;
    minor: number;
    status: VersionStatus;
    status_label: string;
    change_summary: string | null;
    created_by: string;
    effective_at: string | null;
    superseded_at: string | null;
    review_due_at: string | null;
    scheduled_effective_at: string | null;
    review_round: number;
    has_file: boolean;
    has_translation: boolean;
    roster: RosterEntry[];
    comments: ReviewComment[];
    receipts: ReceiptSummary;
    can: VersionAbilities;
    external_review: { locked: boolean; expires_at: string | null } | null;
};

/** Shape of App\Http\Resources\Documents\DocumentEventListResource. */
export type EventRow = {
    id: number;
    type: string;
    label: string;
    summary: string;
    occurred_at: string | null;
    actor: string | null;
    actor_type: 'user' | 'guest' | 'system';
    version: { major: number; minor: number } | null;
    from_status: string | null;
    to_status: string | null;
    imported: boolean;
};

/** Shape of App\Http\Resources\Documents\QueueItemResource. */
export type QueueItem = {
    id: string;
    document: {
        id: string;
        number: string;
        title: string;
        type: string;
        department: string;
        owner: string;
    };
    major: number;
    minor: number;
    status: VersionStatus;
    status_label: string;
    waiting_since: string | null;
    detail: string;
    urgent: boolean;
};

export type TaskKindTab = { value: string; label: string; count: number };

export type TaskKindInfo = {
    value: string;
    label: string;
    description: string;
    empty: string;
    action: string;
};
