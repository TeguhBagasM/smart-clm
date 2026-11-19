<?php

namespace App\Actions\AiTools;

use App\Models\AiTools\DocumentComparison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Removes a comparison, its rows and the uploaded files. Used by the owner and by the retention job.
 */
class DeleteDocumentComparison
{
    public function handle(DocumentComparison $comparison): void
    {
        $paths = $comparison->sources()->whereNotNull('file_path')->pluck('file_path')->all();

        DB::transaction(fn () => $comparison->delete());

        Storage::disk(config('ai.comparison.disk'))->delete($paths);
    }
}
