<?php

namespace App\Actions\AiTools;

use App\Models\AiTools\DocumentComparison;
use App\Support\Comparison\ArticleSplitter;
use Illuminate\Support\Facades\DB;

/**
 * Cuts the extracted text of both sides into articles and stores them.
 */
class SplitComparisonArticles
{
    public function __construct(private readonly ArticleSplitter $splitter) {}

    public function handle(DocumentComparison $comparison): void
    {
        $results = [];

        foreach (['a', 'b'] as $side) {
            $results[$side] = $this->splitter->split((string) $comparison->source($side)->extracted_text);
        }

        DB::transaction(function () use ($comparison, $results): void {
            $comparison->articles()->delete();

            foreach ($results as $side => $result) {
                foreach ($result['articles'] as $index => $article) {
                    $comparison->articles()->create([...$article, 'side' => $side, 'ordinal' => $index + 1]);
                }
            }
        });

        foreach ($results as $side => $result) {
            if ($result['strategy'] === ArticleSplitter::STRATEGY_CHUNKS) {
                $comparison->warn('Pasal tidak terdeteksi di Dokumen '.strtoupper($side).'; dokumen dipotong per bagian sehingga pemasangan kurang akurat.');
            } elseif ($result['strategy'] === ArticleSplitter::STRATEGY_NUMBERED) {
                $comparison->warn('Dokumen '.strtoupper($side).' tidak memakai "Pasal"; dipecah berdasarkan judul bernomor.');
            }
        }
    }
}
