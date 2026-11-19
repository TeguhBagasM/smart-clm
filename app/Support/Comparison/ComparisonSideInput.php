<?php

namespace App\Support\Comparison;

use App\Enums\AiTools\SourceKind;
use App\Models\Dcs\DocumentVersion;
use Illuminate\Http\UploadedFile;

/**
 * One validated side of a new comparison: exactly one of file, version or text is set, matching `kind`.
 */
final readonly class ComparisonSideInput
{
    public function __construct(
        public SourceKind $kind,
        public ?string $label = null,
        public ?UploadedFile $file = null,
        public ?DocumentVersion $version = null,
        public ?string $text = null,
    ) {}
}
