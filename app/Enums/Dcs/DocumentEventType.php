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
}
