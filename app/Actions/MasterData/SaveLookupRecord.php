<?php

namespace App\Actions\MasterData;

use App\Enums\MasterData\MasterList;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MasterData\LookupCode;
use App\Support\MasterData\MasterDataRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Creates or renames one row of a simple list (platform, category, employment
 * type, party type, currency). The code of a default (is_system) row never
 * changes, so the defaults command keeps recognising it. The employee marker
 * of a category or party type is structural and is not edited here.
 */
class SaveLookupRecord
{
    public function __construct(private MasterDataRecorder $recorder) {}

    /**
     * @param  array{code?: string|null, name: string}  $data
     */
    public function handle(User $actor, Tenant $tenant, MasterList $list, array $data, ?Model $record = null): Model
    {
        if (! $list->isSimple()) {
            throw new InvalidArgumentException("{$list->value} has its own save action.");
        }

        if ($record !== null && $record->getAttribute('tenant_id') !== $tenant->id) {
            throw (new ModelNotFoundException)->setModel($list->model());
        }

        $name = trim($data['name']);

        try {
            return DB::transaction(function () use ($actor, $tenant, $list, $data, $name, $record): Model {
                $model = $list->model();
                $before = $record === null ? null : $this->recorder->snapshot($record);
                $record ??= new $model;

                if ($record->exists) {
                    $attributes = ['name' => $name, 'updated_by_id' => $actor->id];

                    if (! $record->getAttribute('is_system')) {
                        $attributes['code'] = $this->code($data, $name, $list);
                    }
                } else {
                    $attributes = [
                        'tenant_id' => $tenant->id, 'name' => $name, 'code' => $this->code($data, $name, $list),
                        'sort_order' => ((int) $model::query()->where('tenant_id', $tenant->id)->max('sort_order')) + 1,
                        'is_active' => true, 'is_system' => false, 'created_by_id' => $actor->id, 'updated_by_id' => $actor->id,
                    ];
                }

                $record->forceFill($attributes)->save();
                $this->recorder->record($actor, $before === null ? 'created' : 'updated', $record, $before, $this->recorder->snapshot($record));

                return $record;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => "{$list->label()}: kode atau nama ini sudah dipakai di perusahaan ini."]);
        }
    }

    /**
     * @param  array{code?: string|null, name: string}  $data
     */
    private function code(array $data, string $name, MasterList $list): string
    {
        $code = trim((string) ($data['code'] ?? ''));

        return mb_strtoupper($code !== '' ? $code : LookupCode::fromName($name, $list->codeLength()));
    }
}
