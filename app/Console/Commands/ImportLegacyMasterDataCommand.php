<?php

namespace App\Console\Commands;

use App\Actions\MasterData\ImportLegacyMasterData;
use App\Actions\MasterData\RollbackLegacyMasterData;
use App\Models\Tenant;
use App\Support\MasterData\Legacy\ImportReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('master-data:import-legacy {--tenant=* : Node tenant ids to import (required)} {--dry-run : Do everything and roll back, to see the report} {--rollback : Take back what was imported for the tenant} {--force-unclean : Import even though the dry run found unknown values or rows that could not be mapped}')]
#[Description('Copy master data (categories, document types, platforms, partners, employees without salary) from the Node database, one tenant at a time')]
class ImportLegacyMasterDataCommand extends Command
{
    public function handle(ImportLegacyMasterData $import, RollbackLegacyMasterData $rollback): int
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

    private function undo(RollbackLegacyMasterData $rollback, Tenant $tenant): bool
    {
        $report = $rollback->handle($tenant, (bool) $this->option('dry-run'));
        $this->show($tenant, $report, $this->option('dry-run') ? 'akan dibatalkan' : 'dibatalkan');

        return $report->errors !== [];
    }

    private function copy(ImportLegacyMasterData $import, Tenant $tenant): bool
    {
        $report = $import->handle($tenant, true);

        if ($this->option('dry-run')) {
            $this->show($tenant, $report, 'akan diimpor');

            return $report->errors !== [];
        }

        if (! $report->isClean() && ! $this->option('force-unclean')) {
            $this->show($tenant, $report, 'akan diimpor');
            $this->components->error("Dry run {$tenant->code} tidak bersih, impor ditolak. Perbaiki daftar acuan, atau pakai --force-unclean untuk tetap mengimpor baris yang bisa dipetakan.");

            return true;
        }

        $report = $import->handle($tenant, false);
        $this->show($tenant, $report, 'diimpor');

        return $report->errors !== [] && ! $this->option('force-unclean');
    }

    private function show(Tenant $tenant, ImportReport $report, string $verb): void
    {
        $this->components->info("PT {$tenant->code}: {$verb}");
        $this->table(['Daftar', 'Hasil', 'Jumlah'], collect($report->counts)->flatMap(fn (array $outcomes, string $list): array => collect($outcomes)->map(fn (int $n, string $outcome): array => [$list, $outcome, $n])->values()->all())->all());

        if ($report->unknown !== []) {
            $this->components->warn('Nilai yang tidak ada di daftar acuan:');
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
