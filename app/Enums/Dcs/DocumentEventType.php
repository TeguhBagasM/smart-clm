<?php

namespace App\Enums\Dcs;

/**
 * Kinds of entries in a document's history (`dcs_document_events`). Slices add
 * their cases as they start writing the events.
 */
enum DocumentEventType: string
{
    case DocumentCreated = 'document.created';
    case NumberAllocated = 'number.allocated';
    case NumberReleased = 'number.released';
    case VersionCreated = 'version.created';
    case ContentComposed = 'content.composed';
    case FileUploaded = 'file.uploaded';
    case ReviewScheduleSet = 'review_schedule.set';
    case Submitted = 'version.submitted';
    case Signed = 'version.signed';
    case Approved = 'version.approved';
    case ChangesRequested = 'version.changes_requested';
    case ReturnedToDraft = 'version.returned_to_draft';
    case CommentAdded = 'comment.added';
    case CommentResolved = 'comment.resolved';
    case ReleaseScheduled = 'release.scheduled';
    case ReleaseScheduleCancelled = 'release.schedule_cancelled';
    case Released = 'version.released';
    case Superseded = 'version.superseded';
    case Withdrawn = 'version.withdrawn';
    case Distributed = 'version.distributed';
    case Acknowledged = 'version.acknowledged';
    case Translated = 'translation.created';
    case ExternalReviewEnabled = 'external_review.enabled';
    case ExternalReviewDisabled = 'external_review.disabled';
    case ExternalReviewUnlocked = 'external_review.unlocked';
    case ExternalReviewApproved = 'external_review.approved';

    /**
     * The name shown to people (Indonesian, as in the interface).
     */
    public function label(): string
    {
        return match ($this) {
            self::DocumentCreated => 'Dokumen dibuat',
            self::NumberAllocated => 'Nomor diberikan',
            self::NumberReleased => 'Nomor dilepas',
            self::VersionCreated => 'Versi dibuat',
            self::ContentComposed => 'Dokumen disusun',
            self::FileUploaded => 'Berkas diunggah',
            self::ReviewScheduleSet => 'Jadwal tinjau ulang diatur',
            self::Submitted => 'Diajukan untuk tanda tangan',
            self::Signed => 'Ditandatangani',
            self::Approved => 'Disetujui penuh',
            self::ChangesRequested => 'Diminta revisi',
            self::ReturnedToDraft => 'Dikembalikan ke draft',
            self::CommentAdded => 'Komentar ditambahkan',
            self::CommentResolved => 'Komentar diselesaikan',
            self::ReleaseScheduled => 'Rilis dijadwalkan',
            self::ReleaseScheduleCancelled => 'Jadwal rilis dibatalkan',
            self::Released => 'Diberlakukan',
            self::Superseded => 'Digantikan versi baru',
            self::Withdrawn => 'Ditarik',
            self::Distributed => 'Didistribusikan',
            self::Acknowledged => 'Dikonfirmasi dibaca',
            self::Translated => 'Terjemahan dibuat',
            self::ExternalReviewEnabled => 'Link review eksternal dibuka',
            self::ExternalReviewDisabled => 'Link review eksternal ditutup',
            self::ExternalReviewUnlocked => 'Review eksternal dibuka kembali',
            self::ExternalReviewApproved => 'Pihak eksternal menyetujui',
        };
    }
}
