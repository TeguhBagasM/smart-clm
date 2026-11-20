<?php

namespace App\Console\Commands;

use App\Actions\Templates\ImportLegacyTemplateLibrary;
use App\Actions\Templates\RollbackLegacyTemplateLibrary;
use App\Models\Tenant;
use App\Support\MasterData\Legacy\ImportReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contract-templates:import-legacy {--tenant=* : Node tenant ids to import (required)} {--dry-run : Do everything and roll back, to see the report} {--rollback : Take back what the import brought in for the tenant}')]
#[Description('Copy the clause library (fields, clauses, templates) from the Node database, one tenant at a time; refused unless the dry run is clean')]
class ImportLegacyTemplateLibraryCommand extends Command
{
    public function handle(ImportLegacyTemplateLibrary $import, RollbackLegacyTemplateLibrary $rollback): int
    {
        $ids = array_values(array_filter((array) $this->option('tenant'), is_string(...)));

        if ($ids === []) {
            $this->components->error('Sebutkan tenant Node yang diimpor, mis. --tenant=t-01. Tidak ada impor massal.');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($ids as $legacyId) {
            $tenant = Tenant::query()->where('legacy_id', $legacyId)->first();

            if ($tenant === null) {
                $this->components->error("Tenant Node \"{$legacyId}\" belum punya padanan di Laravel (tenants.legacy_id). Dilewati.");
                $failed = true;

                continue;
            }

            $failed = ($this->option('rollback') ? $this->undo($rollback, $tenant) : $this->copy($import, $tenant)) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function undo(RollbackLegacyTemplateLibrary $rollback, Tenant $tenant): bool
    {
        $report = $rollback->handle($tenant, (bool) $this->option('dry-run'));
        $this->show($tenant, $report, $this->option('dry-run') ? 'akan dibatalkan' : 'dibatalkan');

        return $report->errors !== [];
    }

    /**
     * A real run first repeats the dry run and goes on only when it is clean.
     */
    private function copy(ImportLegacyTemplateLibrary $import, Tenant $tenant): bool
    {
        $report = $import->handle($tenant, true);

        if ($this->option('dry-run') || ! $report->isClean()) {
            $this->show($tenant, $report, 'akan diimpor');

            if (! $this->option('dry-run')) {
                $this->components->error("Dry run {$tenant->code} tidak bersih, impor ditolak. Perbaiki data perpustakaan di Node sesuai daftar, lalu jalankan --dry-run lagi.");
            }

            return ! $report->isClean();
        }

        $report = $import->handle($tenant, false);
        $this->show($tenant, $report, 'diimpor');

        return $report->errors !== [];
    }

    private function show(Tenant $tenant, ImportReport $report, string $verb): void
    {
        $this->components->info("PT {$tenant->code}: {$verb}");
        $this->table(['Daftar', 'Hasil', 'Jumlah'], collect($report->counts)->flatMap(fn (array $outcomes, string $list): array => collect($outcomes)->map(fn (int $n, string $outcome): array => [$list, $outcome, $n])->values()->all())->all());

        if ($report->unknown !== []) {
            $this->components->warn('Nilai yang tidak ada di perusahaan ini:');
            $this->table(['Asal', 'Nilai', 'Baris'], collect($report->unknown)->flatMap(fn (array $values, string $where): array => collect($values)->map(fn (int $n, string $value): array => [$where, $value, $n])->values()->all())->all());
        }

        foreach ($report->errors as $error) {
            $this->components->error($error);
        }

        foreach ($report->warnings as $warning) {
            $this->components->warn($warning);
        }
    }
}
