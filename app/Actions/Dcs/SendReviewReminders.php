<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\VersionStatus;
use App\Enums\Permission;
use App\Models\Dcs\DocumentVersion;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\Dcs\Notifications\DcsNotifier;

/**
 * Nudges about periodic review: every effective version whose review date is
 * within its reminder window (or past) is announced once per calendar day, to the
 * document's owner and to the people who release documents.
 */
class SendReviewReminders
{
    public function __construct(private DcsNotifier $notifier) {}

    /**
     * @return int how many reminders were sent
     */
    public function handle(): int
    {
        $today = now()->startOfDay();
        $sent = 0;
        $releaseRoleIds = RolePermission::query()->where('permission_key', Permission::DcsRelease->value)->pluck('role_id');

        DocumentVersion::query()
            ->where('status', VersionStatus::Effective->value)
            ->whereNotNull('review_due_at')
            ->with('document')
            ->lazyById()
            ->each(function (DocumentVersion $version) use ($today, &$sent, $releaseRoleIds): void {
                if ($version->last_review_reminder_on?->isSameDay($today)) {
                    return;
                }

                $daysRemaining = (int) round($today->diffInDays($version->review_due_at->copy()->startOfDay(), false));

                if ($daysRemaining > $version->review_reminder_days) {
                    return;
                }

                $document = $version->document;
                $overdue = $daysRemaining < 0;
                $message = $overdue
                    ? "{$document->document_number} ({$document->title}) sudah melewati jadwal tinjau ulang ".abs($daysRemaining).' hari yang lalu.'
                    : "{$document->document_number} ({$document->title}) perlu ditinjau ulang dalam {$daysRemaining} hari.";

                $recipients = User::query()->where('tenant_id', $version->tenant_id)->where('is_active', true)
                    ->where(fn ($query) => $query->whereKey($document->owner_user_id)->orWhereIn('role_id', $releaseRoleIds))->get();

                $this->notifier->notify($recipients, $document, $overdue ? 'Dokumen Lewat Jadwal Tinjau Ulang' : 'Dokumen Perlu Ditinjau Ulang', $message, $overdue ? 'danger' : ($daysRemaining <= 7 ? 'warning' : 'info'));

                $version->forceFill(['last_review_reminder_on' => $today])->save();
                $sent++;
            });

        return $sent;
    }
}
