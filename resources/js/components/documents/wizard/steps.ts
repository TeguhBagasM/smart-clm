import type { Draft } from '@/types';

export type StepKey = 'body' | 'flow' | 'attachments' | 'signers' | 'review';

export type StepInfo = {
    key: StepKey;
    label: string;
    hint: string;
    /** The completeness key the server reports for this step, if it has one. */
    done?: keyof Draft['completeness'];
};

export const steps: StepInfo[] = [
    {
        key: 'body',
        label: 'Isi dokumen',
        hint: 'Tujuan, lingkup, dan bagian lain',
        done: 'body',
    },
    {
        key: 'flow',
        label: 'Diagram alir',
        hint: 'Alur kerja (opsional)',
        done: 'flow',
    },
    {
        key: 'attachments',
        label: 'Lampiran & klausul',
        hint: 'Dokumen pendukung',
        done: 'attachments',
    },
    {
        key: 'signers',
        label: 'Penandatangan',
        hint: 'Siapa yang mengesahkan',
        done: 'signers',
    },
    { key: 'review', label: 'Tinjau & ajukan', hint: 'Susun PDF dan ajukan' },
];
