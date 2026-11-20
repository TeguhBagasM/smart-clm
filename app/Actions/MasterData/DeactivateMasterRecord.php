<?php

namespace App\Actions\MasterData;

use App\Models\User;
use App\Support\MasterData\MasterDataRecorder;
use App\Support\MasterData\Usage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hides a row from new choices. Nothing is deleted: what already uses the
 * value keeps it. Returns how many records use it, so the screen can say so.
 * The row that marks employee documents cannot be hidden.
 */
class DeactivateMasterRecord
{
    public function __construct(private MasterDataRecorder $recorder, private Usage $usage) {}

    /**
     * @return array{usage: int, changed: bool}
     */
    public function handle(User $actor, Model $record): array
    {
        if ($record->getAttribute('is_employee_key') || $record->getAttribute('is_employee')) {
            throw ValidationException::withMessages(['record' => 'Pilihan ini menandai dokumen karyawan, jadi tidak bisa disembunyikan.']);
        }

        $usage = $this->usage->count($record);

        if (! $record->getAttribute('is_active')) {
            return ['usage' => $usage, 'changed' => false];
        }

        DB::transaction(function () use ($actor, $record): void {
            $before = $this->recorder->snapshot($record);
            $record->forceFill(['is_active' => false, 'updated_by_id' => $actor->id])->save();
            $this->recorder->record($actor, 'deactivated', $record, $before, $this->recorder->snapshot($record));
        });

        return ['usage' => $usage, 'changed' => true];
    }
}
