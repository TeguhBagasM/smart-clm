<?php

namespace App\Console\Commands;

use App\Actions\Templates\SeedTenantTemplateLibrary;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contract-templates:seed-defaults {--dry-run : Report what would be added and write nothing} {--tenant=* : Limit to these company codes}')]
#[Description('Give every active company the default contract fields, clauses and draft templates it is missing (idempotent, never overwrites)')]
class SeedTemplateLibraryDefaultsCommand extends Command
{
    public function handle(SeedTenantTemplateLibrary $seed): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $codes = array_map(strtoupper(...), array_values(array_filter($this->option('tenant'), is_string(...))));

        $tenants = Tenant::query()
            ->where('is_active', true)
            ->when($codes !== [], fn ($query) => $query->whereIn('code', $codes))
            ->orderBy('code')
            ->get();

        if ($codes !== [] && $tenants->count() !== count($codes)) {
            $this->components->error('Unknown or inactive company code(s): '.implode(', ', array_diff($codes, $tenants->pluck('code')->all())));

            return self::FAILURE;
        }

        $rows = [];

        foreach ($tenants as $tenant) {
            $report = $seed->handle($tenant, $dryRun);

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
