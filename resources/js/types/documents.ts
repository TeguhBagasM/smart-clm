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
