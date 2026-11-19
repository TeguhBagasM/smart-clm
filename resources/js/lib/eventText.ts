import type { EventRow } from '@/types';

/** What happened, as a plain sentence: "Rina menandatangani", not a system label. */
const sentences: Record<string, (who: string) => string> = {
    'document.created': (who) => `${who} membuat dokumen`,
    'number.allocated': () => 'Nomor dokumen diberikan',
    'number.released': () => 'Nomor dokumen dilepas',
    'version.created': (who) => `${who} membuat versi baru`,
    'content.composed': (who) => `${who} menyusun dokumen menjadi PDF`,
    'file.uploaded': (who) => `${who} mengunggah berkas PDF`,
    'review_schedule.set': (who) => `${who} mengatur jadwal tinjau ulang`,
    'version.submitted': (who) =>
        `${who} mengajukan dokumen untuk ditandatangani`,
    'version.signed': (who) => `${who} menandatangani`,
    'version.approved': () => 'Semua tanda tangan sudah lengkap',
    'version.changes_requested': (who) => `${who} meminta dokumen diperbaiki`,
    'version.returned_to_draft': (who) =>
        `${who} mengembalikan dokumen ke draft`,
    'comment.added': (who) => `${who} menambahkan komentar`,
    'comment.resolved': (who) => `${who} menandai komentar sudah selesai`,
    'release.scheduled': (who) => `${who} menjadwalkan tanggal berlaku`,
    'release.schedule_cancelled': (who) => `${who} membatalkan jadwal berlaku`,
    'version.released': (who) => `${who} memberlakukan dokumen`,
    'version.superseded': () => 'Versi ini digantikan versi yang lebih baru',
    'version.withdrawn': (who) => `${who} menarik dokumen dari peredaran`,
    'version.distributed': (who) => `${who} membagikan dokumen ke penerima`,
    'version.acknowledged': (who) => `${who} mengonfirmasi sudah membaca`,
    'translation.created': (who) => `${who} membuat terjemahan`,
    'external_review.enabled': (who) =>
        `${who} membuka tautan tinjauan untuk pihak luar`,
    'external_review.disabled': (who) =>
        `${who} menutup tautan tinjauan pihak luar`,
    'external_review.unlocked': (who) =>
        `${who} membuka kembali tinjauan pihak luar`,
    'external_review.approved': (who) =>
        `${who} (pihak luar) menyetujui dokumen`,
    'version.signer_reassigned': (who) => `${who} mengganti penandatangan`,
};

export function eventSentence(event: EventRow): string {
    const make = sentences[event.type];

    return make ? make(event.actor ?? 'Sistem') : event.label;
}
