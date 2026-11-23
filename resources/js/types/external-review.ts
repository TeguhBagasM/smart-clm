/** Shape of App\Http\Resources\Documents\ExternalReviewPageResource. */
export type ExternalSection = {
    heading: string;
    content: string;
    table: { columns: string[]; rows: string[][] } | null;
};

export type ExternalComment = {
    id: string;
    author: string;
    external: boolean;
    anchor: { field?: string; sectionHeading?: string; quote?: string };
    body: string;
    status: 'open' | 'resolved';
    created_at: string | null;
};

export type ExternalReviewData = {
    document: {
        title: string;
        number: string;
        type: string;
        major: number;
        minor: number;
        purpose: string;
        scope: string;
        sections: ExternalSection[];
    };
    comments: ExternalComment[];
    locked: boolean;
    expires_at: string | null;
};

/** What an outside party's comment points at. */
export type ExternalAnchor = {
    field: 'general' | 'purpose' | 'scope' | 'section';
    sectionIndex: number | null;
};
