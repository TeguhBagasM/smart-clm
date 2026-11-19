<?php

namespace App\Jobs\AiTools;

use App\Actions\AiTools\AlignComparisonArticles;
use App\Actions\AiTools\AnalyzeComparisonRows;
use App\Actions\AiTools\ComputeComparisonDiffs;
use App\Actions\AiTools\ExtractComparisonText;
use App\Actions\AiTools\SplitComparisonArticles;
use App\Actions\AiTools\WriteStrategicReview;
use App\Enums\AiTools\ComparisonStage;
use App\Enums\AiTools\ComparisonStatus;
use App\Models\AiTools\DocumentComparison;
use App\Support\Ai\InvalidAiOutput;
use App\Support\Ai\LlmException;
use App\Support\Comparison\Extraction\ExtractionException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One step of the comparison pipeline. Steps run as a chain so each has its own timeout and the
 * page can show which one is running; the first failure stops the chain and records a readable reason.
 */
class RunComparisonStage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $comparisonId, public ComparisonStage $stage) {}

    public function handle(): void
    {
        $comparison = DocumentComparison::query()->find($this->comparisonId);

        if ($comparison === null || $comparison->status === ComparisonStatus::Failed) {
            return;
        }

        $comparison->enter($this->stage);

        match ($this->stage) {
            ComparisonStage::Extracting => $this->extract($comparison),
            ComparisonStage::Splitting => app(SplitComparisonArticles::class)->handle($comparison),
            ComparisonStage::Aligning => app(AlignComparisonArticles::class)->handle($comparison),
            ComparisonStage::Diffing => app(ComputeComparisonDiffs::class)->handle($comparison),
            ComparisonStage::Analyzing => app(AnalyzeComparisonRows::class)->handle($comparison),
            ComparisonStage::Summarizing => app(WriteStrategicReview::class)->handle($comparison),
        };

        $comparison->leave($this->stage);

        if ($this->stage === ComparisonStage::Summarizing) {
            $comparison->forceFill(['status' => ComparisonStatus::Completed, 'stage' => null, 'finished_at' => now()])->save();
        }
    }

    public function failed(Throwable $e): void
    {
        $known = $e instanceof ExtractionException || $e instanceof LlmException || $e instanceof InvalidAiOutput;

        if (! $known) {
            Log::error('Comparison stage failed', ['comparison' => $this->comparisonId, 'stage' => $this->stage->value, 'exception' => $e]);
        }

        DocumentComparison::query()->whereKey($this->comparisonId)->first()?->forceFill([
            'status' => ComparisonStatus::Failed,
            'error_message' => ($known ? $e->getMessage() : 'Terjadi kesalahan tak terduga saat memproses perbandingan.').' (tahap: '.$this->stage->label().')',
            'finished_at' => now(),
        ])->save();
    }

    private function extract(DocumentComparison $comparison): void
    {
        $extractor = app(ExtractComparisonText::class);

        foreach (['a' => 15, 'b' => 30] as $side => $progress) {
            $extractor->handle($comparison->source($side));
            $comparison->forceFill(['progress' => $progress])->save();
        }
    }
}
