<?php

namespace App\Actions\Dcs;

use App\Models\Dcs\DocumentType;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\VersionStep;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\RoleKeyResolver;
use Illuminate\Validation\ValidationException;

/**
 * Snapshots the signature columns of a version from its document type's steps,
 * adds the extra reviewers, and records who was assigned to sign each column.
 * The first column goes to the document owner unless someone else is named. A
 * later change to the document type does not touch a version already drafted
 * (until it is composed again). Call it inside the caller's transaction.
 */
class BuildVersionSteps
{
    public function __construct(private RoleKeyResolver $roleKeyResolver) {}

    /**
     * @param  list<string|null>  $assignments  legacy user ids by position in the flat list of columns
     * @param  list<array{label: string, role: string|null, assignedUserId: string|null}>  $extraReviewers
     *
     * @throws ValidationException
     */
    public function handle(Tenant $tenant, DocumentVersion $version, array $assignments, array $extraReviewers): void
    {
        $document = $version->document()->with('type')->firstOrFail();
        $groups = $this->templateGroups($document->type);
        $groups = $groups === [] ? [['heading' => null, 'columns' => [['label' => 'Disetujui oleh', 'roleKey' => 'direktur', 'roleId' => null, 'roleName' => null]]]] : $groups;

        $extras = array_values(array_filter($extraReviewers, fn (array $reviewer): bool => trim($reviewer['label']) !== ''));

        if ($extras !== []) {
            $groups[] = ['heading' => 'Reviewer Tambahan:', 'columns' => array_map(fn (array $reviewer): array => [
                'label' => trim($reviewer['label']),
                'roleKey' => $reviewer['role'] ?? null,
                'roleId' => null,
                'roleName' => null,
                'assignedUserId' => $reviewer['assignedUserId'] ?? null,
            ], $extras)];
        }

        $roleIds = $this->roleKeyResolver->resolve($tenant, $this->roleKeys($groups), 'signatureGroups');
        $users = $this->users($tenant, $assignments, $extras);

        VersionStep::query()->where('version_id', $version->id)->delete();

        $flat = 0;

        foreach ($groups as $groupIndex => $group) {
            foreach ($group['columns'] as $position => $column) {
                $assignedLegacyId = $column['assignedUserId'] ?? ($assignments[$flat] ?? null);
                $assignedUserId = $assignedLegacyId !== null && $assignedLegacyId !== ''
                    ? $users[$assignedLegacyId]
                    : ($flat === 0 ? $document->owner_user_id : null);

                VersionStep::query()->forceCreate([
                    'version_id' => $version->id,
                    'group_position' => $groupIndex + 1,
                    'group_heading' => $group['heading'],
                    'position' => $position + 1,
                    'label' => $column['label'],
                    'role_id' => $column['roleId'] ?? (isset($column['roleKey']) && $column['roleKey'] !== '' ? $roleIds[$column['roleKey']] : null),
                    'role_name' => $column['roleName'],
                    'assigned_user_id' => $assignedUserId,
                    'status' => 'pending',
                ]);

                $flat++;
            }
        }
    }

    /**
     * @return array<int, array{heading: string|null, columns: array<int, array{label: string, roleKey: string|null, roleId: int|null, roleName: string|null, assignedUserId?: string|null}>}>
     */
    private function templateGroups(DocumentType $type): array
    {
        return $type->steps()->with('role')->get()->groupBy('group_position')->map(fn ($steps): array => [
            'heading' => $steps->first()->group_heading,
            'columns' => $steps->map(fn ($step): array => [
                'label' => $step->label,
                'roleKey' => null,
                'roleId' => $step->role_id,
                'roleName' => $step->role_name,
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * @param  array<int, array{heading: string|null, columns: array<int, array<string, mixed>>}>  $groups
     * @return list<string>
     */
    private function roleKeys(array $groups): array
    {
        $keys = [];

        foreach ($groups as $group) {
            foreach ($group['columns'] as $column) {
                if (($column['roleKey'] ?? '') !== '' && ($column['roleKey'] ?? null) !== null) {
                    $keys[] = $column['roleKey'];
                }
            }
        }

        return $keys;
    }

    /**
     * Legacy ids named as signers, mapped to users of the tenant.
     *
     * @param  list<string|null>  $assignments
     * @param  list<array{label: string, role: string|null, assignedUserId: string|null}>  $extras
     * @return array<string, int>
     *
     * @throws ValidationException
     */
    private function users(Tenant $tenant, array $assignments, array $extras): array
    {
        $legacyIds = array_values(array_unique(array_filter(
            [...$assignments, ...array_column($extras, 'assignedUserId')],
            fn (?string $id): bool => $id !== null && $id !== '',
        )));

        $users = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereApiId($legacyIds)
            ->get(['id', 'legacy_id'])
            ->mapWithKeys(fn (User $user): array => [$user->apiId() => $user->id])
            ->all();

        $unknown = array_diff($legacyIds, array_keys($users));

        if ($unknown !== []) {
            throw ValidationException::withMessages(['signatureAssignments' => 'Penandatangan tidak ditemukan: '.implode(', ', $unknown).'.']);
        }

        return $users;
    }
}
