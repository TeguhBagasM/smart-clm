import type { FlowStepType } from '@/types';

/** Names of the step types, in Indonesian. */
export const TYPE_LABELS: Record<FlowStepType, string> = {
    start: 'Mulai',
    end: 'Selesai',
    process: 'Proses',
    decision: 'Keputusan',
    document: 'Dokumen',
    data: 'Data (input/output)',
    connector: 'Penghubung',
    predefined: 'Proses terdefinisi',
};

/**
 * The common symbols offered on the palette. "Proses terdefinisi" is left
 * out; older drafts that use it still draw and print.
 */
export const PALETTE: { type: FlowStepType; label: string; hint: string }[] = [
    { type: 'start', label: 'Mulai / Selesai', hint: 'Awal atau akhir alur' },
    { type: 'process', label: 'Proses', hint: 'Kegiatan atau tindakan' },
    { type: 'decision', label: 'Keputusan', hint: 'Pertanyaan Ya / Tidak' },
    { type: 'document', label: 'Dokumen', hint: 'Formulir atau dokumen' },
    { type: 'data', label: 'Data', hint: 'Input atau output data' },
    { type: 'connector', label: 'Penghubung', hint: 'Lanjut di tempat lain' },
];

/** Types the inspector offers; the step's own type stays listed even if it is not common. */
export function typeOptions(current: FlowStepType): FlowStepType[] {
    const common: FlowStepType[] = [
        'start',
        'end',
        'process',
        'decision',
        'document',
        'data',
        'connector',
    ];

    return common.includes(current) ? common : [...common, current];
}

/** What can follow a step in the "Langkah berikutnya" menu. */
export const NEXT_SYMBOLS: { type: FlowStepType; label: string }[] = [
    { type: 'process', label: 'Proses' },
    { type: 'decision', label: 'Keputusan' },
    { type: 'document', label: 'Dokumen' },
    { type: 'data', label: 'Data' },
    { type: 'connector', label: 'Penghubung' },
    { type: 'end', label: 'Selesai' },
];
