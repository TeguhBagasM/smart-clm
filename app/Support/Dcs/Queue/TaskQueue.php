<?php

namespace App\Support\Dcs\Queue;

use App\Enums\Dcs\ReceiptStatus;
use App\Enums\Dcs\VersionStatus;
use App\Enums\Permission;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which versions are waiting for a person, by kind of task. Always limited to
 * the person's own company (a super admin, who has none, has no tasks).
 *
 * Signing is decided from the signing sheet: the column whose turn it is must be
 * assigned to the person, or have their role. A column anyone may sign is not
 * listed here (it would be on everyone's list); it stays reachable from the
 * document list.
 */
class TaskQueue
{
    /**
     * @return Builder<DocumentVersion>
     */
    public function for(User $user, TaskKind $kind): Builder
    {
        $query = DocumentVersion::query()->where('dcs_document_versions.tenant_id', $user->tenant_id ?? 0);

        return match ($kind) {
            TaskKind::Sign => $query->whereIn('dcs_document_versions.id', $this->awaitingSignature($user)),
            TaskKind::Revise => $query->where('status', VersionStatus::Draft->value)
                ->whereHas('document', fn (Builder $document) => $document->where('owner_user_id', $user->id))
                ->whereHas('reviewComments', fn (Builder $comment) => $comment->where('status', 'open')),
            TaskKind::Release => $user->can(Permission::DcsRelease)
                ? $query->where('status', VersionStatus::Approved->value)
                : $query->whereRaw('1 = 0'),
            TaskKind::Read => $query->where('status', VersionStatus::Effective->value)
                ->whereExists(fn ($receipt) => $receipt->selectRaw('1')->from('dcs_read_receipts')->whereColumn('dcs_read_receipts.version_id', 'dcs_document_versions.id')
                    ->where('dcs_read_receipts.user_id', $user->id)->where('dcs_read_receipts.status', '!=', ReceiptStatus::Acknowledged->value)),
            TaskKind::Review => $query->where('status', VersionStatus::Effective->value)->whereNotNull('review_due_at')
                ->whereRaw('review_due_at <= ?', [now()->addDays(30)])
                ->whereHas('document', fn (Builder $document) => $document->where('owner_user_id', $user->id)),
        };
    }

    /**
     * How many tasks of each kind, for the tabs.
     *
     * @return array<string, int>
     */
    public function counts(User $user): array
    {
        $counts = [];

        foreach (TaskKind::cases() as $kind) {
            $counts[$kind->value] = $this->for($user, $kind)->count();
        }

        return $counts;
    }

    /**
     * Ids of the versions under review whose current column is the person's.
     *
     * @return array<int, int>
     */
    private function awaitingSignature(User $user): array
    {
        if ($user->tenant_id === null) {
            return [];
        }

        return DocumentVersion::query()->where('tenant_id', $user->tenant_id)->where('status', VersionStatus::UnderReview->value)
            ->withCount(['liveApprovals as signed_count'])->with('steps')->get()
            ->filter(function (DocumentVersion $version) use ($user): bool {
                $step = $version->steps->values()->get((int) $version->getAttribute('signed_count'));

                return $step !== null && ($step->assigned_user_id !== null ? $step->assigned_user_id === $user->id : ($step->role_id !== null && $step->role_id === $user->role_id));
            })->pluck('id')->values()->all();
    }
}
