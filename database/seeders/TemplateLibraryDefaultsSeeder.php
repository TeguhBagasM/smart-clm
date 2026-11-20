<?php

namespace Database\Seeders;

use App\Actions\Templates\SeedTenantTemplateLibrary;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Gives every active company the default fields, clauses and (draft) templates.
 * Runs after MasterDataDefaultsSeeder, whose categories it needs. Safe in every
 * environment: it only adds what is missing (see SeedTenantTemplateLibrary).
 */
class TemplateLibraryDefaultsSeeder extends Seeder
{
    public function run(SeedTenantTemplateLibrary $seedTenantTemplateLibrary): void
    {
        Tenant::query()->where('is_active', true)->each(
            fn (Tenant $tenant) => $seedTenantTemplateLibrary->handle($tenant),
        );
    }
}
