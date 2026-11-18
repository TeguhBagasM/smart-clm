<?php

namespace App\Console\Commands;

use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dcs:seed-defaults')]
#[Description('Give every active tenant the default DCS document types and numbering rule (idempotent)')]
class SeedDcsDefaultsCommand extends Command
{
    public function handle(SeedDcsTenantDefaults $seedDcsTenantDefaults): int
    {
        $count = 0;

        Tenant::query()->where('is_active', true)->each(function (Tenant $tenant) use ($seedDcsTenantDefaults, &$count): void {
            $seedDcsTenantDefaults->handle($tenant);
            $count++;
        });

        $this->components->info("DCS defaults ensured for {$count} tenant(s).");

        return self::SUCCESS;
    }
}
