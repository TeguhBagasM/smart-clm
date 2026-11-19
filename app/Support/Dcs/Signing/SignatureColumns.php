<?php

namespace App\Support\Dcs\Signing;

use App\Models\Dcs\DocumentVersion;
use Illuminate\Support\Collection;

/**
 * Resolves who signs a version, in order. The columns are the version's
 * snapshotted steps; a version that never had content composed (a plain
 * uploaded PDF) falls back to one generic column that any user may sign, as the
 * Node API did.
 */
class SignatureColumns
{
    /**
     * @return Collection<int, SignatureColumn>
     */
    public static function of(DocumentVersion $version): Collection
    {
        $steps = $version->steps()->with('role')->get();

        if ($steps->isEmpty()) {
            return collect([new SignatureColumn(1, 'Disetujui oleh', null, null, null, null, null, 1)]);
        }

        return $steps->values()->map(fn ($step, int $index): SignatureColumn => new SignatureColumn(
            $index + 1,
            $step->label,
            $step->role_id,
            $step->role?->key,
            $step->role_name,
            $step->assigned_user_id,
            $step->group_heading,
            $step->group_position,
            $step->fill_mode,
            $step->allow_creator,
        ));
    }

    /**
     * The columns grouped for the PDF engine, which draws one heading per group.
     *
     * @param  Collection<int, SignatureColumn>  $columns
     * @return array<int, array{heading?: string, columns: array<int, array<string, string>>}>
     */
    public static function groups(Collection $columns): array
    {
        return $columns->groupBy('groupPosition')->map(fn (Collection $group): array => [
            ...($group->first()->groupHeading !== null ? ['heading' => $group->first()->groupHeading] : []),
            'columns' => $group->map(fn (SignatureColumn $column): array => [
                'label' => $column->label,
                'role' => $column->roleKey ?? '',
                ...($column->roleName !== null ? ['roleName' => $column->roleName] : []),
            ])->values()->all(),
        ])->values()->all();
    }
}
