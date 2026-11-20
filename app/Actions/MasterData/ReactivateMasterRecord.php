<?php

namespace App\Actions\MasterData;

use App\Models\User;
use App\Support\MasterData\MasterDataRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Brings a hidden row back into the choices.
 */
class ReactivateMasterRecord
{
    public function __construct(private MasterDataRecorder $recorder) {}

    public function handle(User $actor, Model $record): bool
    {
        if ($record->getAttribute('is_active')) {
            return false;
        }

        DB::transaction(function () use ($actor, $record): void {
            $before = $this->recorder->snapshot($record);
            $record->forceFill(['is_active' => true, 'updated_by_id' => $actor->id])->save();
            $this->recorder->record($actor, 'reactivated', $record, $before, $this->recorder->snapshot($record));
        });

        return true;
    }
}
