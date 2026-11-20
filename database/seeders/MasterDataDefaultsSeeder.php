<?php

namespace Database\Seeders;

use App\Actions\MasterData\SeedTenantMasterData;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Gives every active company the default master data lists. Safe in every
 * environment: it only adds what is missing (see SeedTenantMasterData).
 */
class MasterDataDefaultsSeeder extends Seeder
{
    public function run(SeedTenantMasterData $seedTenantMasterData): void
    {
        Tenant::query()->where('is_active', true)->each(
            fn (Tenant $tenant) => $seedTenantMasterData->handle($tenant),
        );
    }
}
