<?php

namespace App\Actions\MasterData;

use App\Enums\MasterData\DocumentModule;
use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\ContractDocumentType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MasterData\MasterDataRecorder;
use App\Support\MasterData\OwnedIds;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or changes a document type with the categories it is offered for
 * (none = every category, as in Node) and its extra number codes. The
 * categories must belong to the same company. The code of a default row
 * never changes.
 */
class SaveDocumentType
{
    public function __construct(private MasterDataRecorder $recorder, private OwnedIds $ownedIds) {}

    /**
     * @param  array{code: string, name: string, module: DocumentModule, retention_years?: int|null, category_ids?: list<int>, extra_codes?: list<array{label: string, value: string}>}  $data
     */
    public function handle(User $actor, Tenant $tenant, array $data, ?ContractDocumentType $type = null): ContractDocumentType
    {
        if ($type !== null && $type->tenant_id !== $tenant->id) {
            throw (new ModelNotFoundException)->setModel(ContractDocumentType::class);
        }

        $categoryIds = $this->ownedIds->ensure(ContractCategory::class, $tenant, $data['category_ids'] ?? [], 'category_ids', 'Ada kategori yang bukan milik perusahaan ini.');

        try {
            return DB::transaction(function () use ($actor, $tenant, $data, $type, $categoryIds): ContractDocumentType {
                $before = $type === null ? null : $this->state($type);
                $type ??= new ContractDocumentType;
                $fields = [
                    'name' => trim($data['name']), 'module' => $data['module'],
                    'retention_years' => $data['retention_years'] ?? null, 'updated_by_id' => $actor->id,
                ];

                if (! $type->exists) {
                    $fields += [
                        'tenant_id' => $tenant->id, 'is_active' => true, 'is_system' => false, 'created_by_id' => $actor->id,
                        'sort_order' => ((int) ContractDocumentType::query()->where('tenant_id', $tenant->id)->max('sort_order')) + 1,
                    ];
                }

                if (! $type->is_system) {
                    $fields['code'] = mb_strtoupper(trim($data['code']));
                }

                $type->forceFill($fields)->save();
                $type->categories()->sync($categoryIds);
                $type->extraCodes()->delete();
                $type->extraCodes()->createMany(array_map(
                    fn (array $code, int $position): array => ['label' => trim($code['label']), 'value' => trim($code['value']), 'sort_order' => $position + 1],
                    $data['extra_codes'] ?? [], array_keys($data['extra_codes'] ?? []),
                ));
                $this->recorder->record($actor, $before === null ? 'created' : 'updated', $type, $before, $this->state($type->refresh()));

                return $type;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Kode atau nama jenis ini sudah dipakai di perusahaan ini.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function state(ContractDocumentType $type): array
    {
        return $this->recorder->snapshot($type) + [
            'category_ids' => $type->categories()->orderBy('contract_categories.id')->pluck('contract_categories.id')->all(),
            'extra_codes' => $type->extraCodes()->get(['label', 'value'])->toArray(),
        ];
    }
}
