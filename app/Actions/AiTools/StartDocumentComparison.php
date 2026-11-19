<?php

namespace App\Actions\AiTools;

use App\Enums\AiTools\ComparisonStage;
use App\Enums\AiTools\ComparisonStatus;
use App\Enums\AiTools\SourceKind;
use App\Jobs\AiTools\RunComparisonStage;
use App\Models\AiTools\DocumentComparison;
use App\Models\User;
use App\Support\Comparison\ComparisonSideInput;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates a comparison with its two sources and queues the pipeline. Nothing heavy runs here.
 * A DCS version must belong to the user's company; the caller has already checked that.
 */
class StartDocumentComparison
{
    public function handle(User $user, ComparisonSideInput $sideA, ComparisonSideInput $sideB): DocumentComparison
    {
        $comparison = DB::transaction(function () use ($user, $sideA, $sideB): DocumentComparison {
            $comparison = DocumentComparison::create([
                'tenant_id' => $user->tenant_id,
                'created_by_id' => $user->id,
                'status' => ComparisonStatus::Queued,
                'label_a' => $this->label($sideA, 'Dokumen A'),
                'label_b' => $this->label($sideB, 'Dokumen B'),
            ]);

            foreach (['a' => $sideA, 'b' => $sideB] as $side => $input) {
                $comparison->sources()->create($this->source($comparison, $side, $input));
            }

            return $comparison;
        });

        Bus::chain(array_map(
            fn (ComparisonStage $stage): RunComparisonStage => new RunComparisonStage($comparison->id, $stage),
            ComparisonStage::cases(),
        ))->dispatch();

        return $comparison;
    }

    /**
     * @return array<string, mixed>
     */
    private function source(DocumentComparison $comparison, string $side, ComparisonSideInput $input): array
    {
        $base = ['side' => $side, 'kind' => $input->kind];

        return match ($input->kind) {
            SourceKind::Paste => [...$base, 'pasted_text' => (string) $input->text],
            SourceKind::DcsVersion => [...$base, 'dcs_version_id' => $input->version?->id],
            SourceKind::Upload => [...$base, ...$this->store($comparison, $side, $input->file ?? throw new InvalidArgumentException('Upload without a file.'))],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function store(DocumentComparison $comparison, string $side, UploadedFile $file): array
    {
        $path = "{$comparison->tenant_id}/{$comparison->uuid}/{$side}-".Str::random(8).'.pdf';
        Storage::disk(config('ai.comparison.disk'))->putFileAs(dirname($path), $file, basename($path));

        return [
            'file_path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 280, ''),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'size_bytes' => $file->getSize(),
        ];
    }

    private function label(ComparisonSideInput $input, string $fallback): string
    {
        if (filled($input->label)) {
            return Str::limit(trim((string) $input->label), 290, '');
        }

        return match ($input->kind) {
            SourceKind::Upload => Str::limit(pathinfo((string) $input->file?->getClientOriginalName(), PATHINFO_FILENAME), 290, ''),
            SourceKind::DcsVersion => Str::limit($input->version?->document->document_number.' — '.$input->version?->document->title, 290, ''),
            SourceKind::Paste => $fallback,
        };
    }
}
