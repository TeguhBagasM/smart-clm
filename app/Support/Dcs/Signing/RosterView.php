<?php

namespace App\Support\Dcs\Signing;

use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\ApiDate;

/**
 * The signing sheet of a version as the screens show it: every column in
 * order with who it is for and who signed it, and which one is waiting now.
 * Worked out when read; nothing "pending" is stored.
 */
class RosterView
{
    /**
     * @return array<int, array{order: int, label: string, role: string|null, assigned_to: string|null, status: string, signed_by: string|null, signed_at: string|null, is_current: bool}>
     */
    public static function for(DocumentVersion $version): array
    {
        $approvals = $version->liveApprovals()->with('approver')->get()->keyBy('step_order');
        $steps = $version->steps()->with(['role', 'assignedUser'])->get()->values();
        $waiting = $version->status === VersionStatus::UnderReview;
        $current = null;

        $columns = $steps->isEmpty()
            ? [['label' => 'Disetujui oleh', 'role' => null, 'assigned_to' => null]]
            : $steps->map(fn ($step): array => ['label' => $step->label, 'role' => $step->role_name ?? $step->role?->name, 'assigned_to' => $step->assignedUser?->name])->all();

        $entries = [];

        foreach ($columns as $index => $column) {
            $approval = $approvals->get($index + 1);
            $isCurrent = $waiting && $approval === null && $current === null;
            $current = $isCurrent ? $index : $current;

            $entries[] = [
                'order' => $index + 1,
                ...$column,
                'status' => $approval === null ? 'pending' : 'approved',
                'signed_by' => $approval?->approver->name,
                'signed_at' => ApiDate::format($approval?->decided_at),
                'is_current' => $isCurrent,
            ];
        }

        return $entries;
    }
}
