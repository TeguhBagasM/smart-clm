<?php

namespace App\Console\Commands;

use App\Actions\Dcs\ExportLegacyTenant;
use App\Console\Commands\Concerns\PrintsLegacyReport;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dcs:export-legacy {--tenant=* : Node tenant ids to export (default: every tenant with a legacy id)} {--dry-run : Run everything and roll back, to see the report} {--node-stopped : Confirm that the Node application is stopped and no longer writing to its DCS tables} {--skip-files : Do not copy files (hashes are still checked)}')]
#[Description('Rollback: write Smart DCS data from Laravel back into the Node database, one tenant at a time')]
class ExportLegacyDcsCommand extends Command
{
    use PrintsLegacyReport;

    public function handle(ExportLegacyTenant $exportLegacyTenant): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('node-stopped')) {
            $this->components->error('Ekspor menulis ke database Node. Hentikan Node lebih dulu, lalu tambahkan --node-stopped (atau pakai --dry-run untuk melihat laporan saja).');

            return self::FAILURE;
        }

        $requested = array_values(array_filter((array) $this->option('tenant')));
        $tenants = Tenant::query()->whereNotNull('legacy_id')->when($requested !== [], fn ($query) => $query->whereIn('legacy_id', $requested))->orderBy('id')->get();
        $missing = array_diff($requested, $tenants->pluck('legacy_id')->all());
        $failed = false;

        foreach ($missing as $legacyId) {
            $this->components->error("Tenant Node \"{$legacyId}\" tidak punya padanan di Laravel (tenants.legacy_id). Dilewati.");
            $failed = true;
        }

        foreach ($tenants as $tenant) {
            $report = $exportLegacyTenant->handle($tenant, $dryRun, ! $this->option('skip-files'));
            $this->printReport($report, 'diekspor');
            $failed = $failed || $report->hasErrors();
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
