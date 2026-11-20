<?php

namespace App\Console\Commands;

use App\Actions\MasterData\SeedTenantMasterData;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('master-data:seed-defaults {--dry-run : Report what would be added and write nothing} {--tenant=* : Limit to these company codes}')]
#[Description('Give every active company the default master data lists it is missing (idempotent, never overwrites)')]
class SeedMasterDataDefaultsCommand extends Command
{
    public function handle(SeedTenantMasterData $seedTenantMasterData): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $codes = array_map(strtoupper(...), array_values(array_filter($this->option('tenant'), is_string(...))));

        $tenants = Tenant::query()
            ->where('is_active', true)
            ->when($codes !== [], fn ($query) => $query->whereIn('code', $codes))
            ->orderBy('code')
            ->get();

        if ($codes !== [] && $tenants->count() !== count($codes)) {
            $missing = array_diff($codes, $tenants->pluck('code')->all());
            $this->components->error('Unknown or inactive company code(s): '.implode(', ', $missing));

            return self::FAILURE;
        }

        $rows = [];

        foreach ($tenants as $tenant) {
            $report = $seedTenantMasterData->handle($tenant, $dryRun);

            foreach ($report['added'] as $list => $names) {
                $rows[] = [$tenant->code, $list, count($names), implode(', ', $names)];
            }

            foreach ($report['warnings'] as $warning) {
                $this->components->warn("{$tenant->code}: {$warning}");
            }
        }

        $this->table(['Company', 'List', $dryRun ? 'Would add' : 'Added', 'Values'], $rows);
        $this->components->info(($dryRun ? 'Dry run, nothing written. ' : '')."Checked {$tenants->count()} company(ies).");

        return self::SUCCESS;
    }
}
