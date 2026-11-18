<?php

namespace App\Console\Commands\Concerns;

use App\Support\Dcs\Legacy\ImportReport;
use Illuminate\Console\Command;

/**
 * Prints the report of a legacy import or export: the count table and every issue.
 *
 * @mixin Command
 */
trait PrintsLegacyReport
{
    private function printReport(ImportReport $report, string $doneLabel): void
    {
        $this->newLine();
        $this->components->twoColumnDetail("<fg=white;options=bold>Tenant {$report->legacyTenantId}</>", $report->committed ? "<fg=green>{$doneLabel}</>" : ($report->hasErrors() ? '<fg=red>GAGAL, tidak ada yang berubah</>' : '<fg=yellow>dry-run, tidak ada yang disimpan</>'));
        $this->table(['Entitas', 'Sumber', 'Diproses', 'Dilewati'], collect($report->counts)->map(fn (array $count, string $entity): array => [$entity, $count['source'], $count['imported'], $count['skipped']])->values()->all());

        foreach ($report->issues as $issue) {
            $issue['level'] === 'error' ? $this->components->error($issue['message']) : $this->components->warn($issue['message']);
        }
    }
}
