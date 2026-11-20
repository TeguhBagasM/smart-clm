<?php

namespace App\Actions\MasterData;

use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MasterData\MasterDataRecorder;
use App\Support\MasterData\OwnedIds;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or renames a partner group and sets the platforms it applies to
 * (none = every platform of the company, as in Node). Platforms must belong
 * to the same company.
 */
class SavePartnerGroup
{
    public function __construct(private MasterDataRecorder $recorder, private OwnedIds $ownedIds) {}

    /**
     * @param  array{name: string, platform_ids?: list<int>}  $data
     */
    public function handle(User $actor, Tenant $tenant, array $data, ?PartnerGroup $group = null): PartnerGroup
    {
        if ($group !== null && $group->tenant_id !== $tenant->id) {
            throw (new ModelNotFoundException)->setModel(PartnerGroup::class);
        }

        $platformIds = $this->ownedIds->ensure(TenantPlatform::class, $tenant, $data['platform_ids'] ?? [], 'platform_ids', 'Ada platform yang bukan milik perusahaan ini.');

        try {
            return DB::transaction(function () use ($actor, $tenant, $data, $group, $platformIds): PartnerGroup {
                $before = $group === null ? null : $this->state($group);
                $group ??= new PartnerGroup;
                $fields = ['name' => trim($data['name']), 'updated_by_id' => $actor->id];

                if (! $group->exists) {
                    $fields += [
                        'tenant_id' => $tenant->id, 'is_active' => true, 'created_by_id' => $actor->id,
                        'sort_order' => ((int) PartnerGroup::query()->where('tenant_id', $tenant->id)->max('sort_order')) + 1,
                    ];
                }

                $group->forceFill($fields)->save();
                $group->platforms()->sync($platformIds);
                $this->recorder->record($actor, $before === null ? 'created' : 'updated', $group, $before, $this->state($group));

                return $group;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Kelompok dengan nama ini sudah ada di perusahaan ini.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function state(PartnerGroup $group): array
    {
        return $this->recorder->snapshot($group) + ['platform_ids' => $group->platforms()->orderBy('tenant_platforms.id')->pluck('tenant_platforms.id')->all()];
    }
}
