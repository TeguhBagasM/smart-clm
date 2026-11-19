<?php

namespace App\Console\Commands;

use App\Actions\AiTools\DeleteDocumentComparison;
use App\Models\AiTools\DocumentComparison;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('comparisons:prune')]
#[Description('Delete document comparisons (and their uploaded files) older than the retention period')]
class PruneDocumentComparisons extends Command
{
    public function handle(DeleteDocumentComparison $delete): int
    {
        $days = (int) config('ai.comparison.retention_days');

        if ($days <= 0) {
            $this->info('Retention is disabled.');

            return self::SUCCESS;
        }

        $count = 0;

        DocumentComparison::query()->where('created_at', '<', now()->subDays($days))->each(function (DocumentComparison $comparison) use ($delete, &$count): void {
            $delete->handle($comparison);
            $count++;
        });

        $this->info("Deleted {$count} comparison(s).");

        return self::SUCCESS;
    }
}
