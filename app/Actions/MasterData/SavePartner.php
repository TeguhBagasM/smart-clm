<?php

namespace App\Actions\MasterData;

use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MasterData\MasterDataRecorder;
use App\Support\MasterData\OwnedIds;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or changes a partner (name and group only). The group must belong
 * to the same company; a partner may move to another group of that company.
 */
class SavePartner
{
    public function __construct(private MasterDataRecorder $recorder, private OwnedIds $ownedIds) {}

    /**
     * @param  array{name: string, partner_group_id: int}  $data
     */
    public function handle(User $actor, Tenant $tenant, array $data, ?Partner $partner = null): Partner
    {
        if ($partner !== null && $partner->tenant_id !== $tenant->id) {
            throw (new ModelNotFoundException)->setModel(Partner::class);
        }

        [$groupId] = $this->ownedIds->ensure(PartnerGroup::class, $tenant, [$data['partner_group_id']], 'partner_group_id', 'Kelompok mitra ini bukan milik perusahaan ini.');

        try {
            return DB::transaction(function () use ($actor, $tenant, $data, $partner, $groupId): Partner {
                $before = $partner === null ? null : $this->recorder->snapshot($partner);
                $partner ??= new Partner;
                $fields = ['name' => trim($data['name']), 'partner_group_id' => $groupId, 'updated_by_id' => $actor->id];

                if (! $partner->exists) {
                    $fields += [
                        'tenant_id' => $tenant->id, 'is_active' => true, 'created_by_id' => $actor->id,
                        'sort_order' => ((int) Partner::query()->where('partner_group_id', $groupId)->max('sort_order')) + 1,
                    ];
                }

                $partner->forceFill($fields)->save();
                $this->recorder->record($actor, $before === null ? 'created' : 'updated', $partner, $before, $this->recorder->snapshot($partner));

                return $partner;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Mitra dengan nama ini sudah ada di kelompok tersebut.']);
        }
    }
}
