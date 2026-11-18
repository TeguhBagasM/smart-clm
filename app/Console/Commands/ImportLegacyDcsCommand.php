<?php

namespace App\Console\Commands;

use App\Actions\Dcs\ImportLegacyTenant;
use App\Console\Commands\Concerns\PrintsLegacyReport;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('dcs:import-legacy {--tenant=* : Node tenant ids to import (default: every tenant found in the source)} {--settings= : JSON file with the Node settings per tenant id} {--dry-run : Run everything and roll back, to see the report} {--force : Overwrite a tenant that already has live activity in Laravel} {--skip-files : Do not copy files (hashes are still checked)}')]
#[Description('Copy Smart DCS data (documents, versions, signatures, comments, numbering) from the Node database into Laravel, one tenant at a time')]
class ImportLegacyDcsCommand extends Command
{
    use PrintsLegacyReport;

    public function handle(ImportLegacyTenant $importLegacyTenant): int
    {
        $settings = $this->settings();

        if ($settings === false) {
            return self::FAILURE;
        }

        $failed = false;

        foreach ($this->legacyTenantIds() as $legacyId) {
            $tenant = Tenant::query()->where('legacy_id', $legacyId)->first();

            if ($tenant === null) {
                $this->components->error("Tenant Node \"{$legacyId}\" belum punya padanan di Laravel (tenants.legacy_id). Dilewati.");
                $failed = true;

                continue;
            }

            $report = $importLegacyTenant->handle($tenant, $settings[$legacyId] ?? null, (bool) $this->option('dry-run'), ! $this->option('skip-files'), (bool) $this->option('force'));
            $this->printReport($report, 'diimpor');
            $failed = $failed || $report->hasErrors();
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, array<string, mixed>>|false
     */
    private function settings(): array|false
    {
        $path = $this->option('settings');

        if ($path === null) {
            return [];
        }

        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($decoded)) {
            $this->components->error("Berkas pengaturan \"{$path}\" tidak ada atau bukan JSON yang valid.");

            return false;
        }

        return $decoded;
    }

    /**
     * @return array<int, string>
     */
    private function legacyTenantIds(): array
    {
        $requested = array_values(array_filter((array) $this->option('tenant')));

        if ($requested !== []) {
            return $requested;
        }

        $source = DB::connection(config('dcs.legacy.connection'));

        return collect(['dcs_document_types', 'dcs_documents', 'dcs_clauses'])
            ->flatMap(fn (string $table) => $source->table($table)->distinct()->pluck('tenant_id'))
            ->map(fn ($id): string => (string) $id)->unique()->sort()->values()->toArray();
    }
}
