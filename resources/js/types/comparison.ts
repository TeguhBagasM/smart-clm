export type ComparisonStatusValue =
    | 'queued'
    | 'processing'
    | 'completed'
    | 'failed';

export type ChangeKindValue = 'added' | 'removed' | 'changed' | 'unchanged';

export type ComparisonListItem = {
    id: string;
    label_a: string;
    label_b: string;
    status: { value: ComparisonStatusValue; label: string };
    progress: number;
    created_at: string | null;
    finished_at: string | null;
};

/** One run of words: unchanged, removed from A or added in B. */
export type DiffRun = { t: string; o: 'eq' | 'del' | 'ins' };

export type ComparisonArticleRef = {
    label: string | null;
    heading: string | null;
};

export type ComparisonRow = {
    no: number;
    a: ComparisonArticleRef | null;
    b: ComparisonArticleRef | null;
    kind: { value: ChangeKindValue; label: string };
    diff: { a: DiffRun[]; b: DiffRun[] } | null;
    explanation: string | null;
};

export type StrategicItem = { judul: string; pasal: string[]; uraian: string };

export type ComparisonSummary = {
    counts: Record<ChangeKindValue, number>;
    ketentuan_tetap: { a: string | null; b: string | null }[];
    pokok_perubahan: string;
    norma_baru_terpenting: StrategicItem[];
    dihapus_penting: StrategicItem[];
    dampak_risiko: string[];
    rekomendasi: string[];
};

export type ComparisonSourceInfo = {
    side: 'a' | 'b';
    kind: 'upload' | 'dcs_version' | 'paste';
    name: string | null;
    pages: number | null;
    method: string | null;
};

export type ComparisonDetail = Omit<ComparisonListItem, 'status'> & {
    status: { value: ComparisonStatusValue; label: string };
    stage: { value: string; label: string } | null;
    error: string | null;
    warnings: string[];
    sources: ComparisonSourceInfo[];
    summary: ComparisonSummary | null;
    rows: ComparisonRow[];
};

export type ArchiveOption = {
    id: string;
    label: string;
    version: string;
    status: string;
};
