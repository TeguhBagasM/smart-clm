<?php

namespace Tests\Support;

use App\Support\Dcs\Pdf\PdfEngine;
use App\Support\Dcs\Pdf\PdfEngineException;

/**
 * Stands in for the Node PDF engine in tests: returns a small valid PDF and
 * remembers what it was asked to compose.
 */
class FakePdfEngine implements PdfEngine
{
    /** @var list<array<string, mixed>> */
    public array $composed = [];

    public bool $fail = false;

    public function compose(array $input): array
    {
        if ($this->fail) {
            throw new PdfEngineException('The PDF engine answered 500: boom');
        }

        $this->composed[] = $input;

        return [
            'pdf' => self::pdf('composed '.count($this->composed)),
            'signatureSheetPageIndex' => $input['signatureGroups'] === [] ? null : 1,
            'sigLayout' => ['colW' => 170],
            'headerLayout' => $input['headerStyle'] === 'iso' ? ['status' => ['x' => 1, 'y' => 2, 'size' => 8], 'issued' => ['x' => 3, 'y' => 4, 'size' => 8]] : null,
        ];
    }

    /** @var list<array<string, mixed>> */
    public array $stamped = [];

    public function stampSignature(array $input): string
    {
        if ($this->fail) {
            throw new PdfEngineException('The PDF engine answered 500: boom');
        }

        $this->stamped[] = $input;

        return $input['pdf']."\n% signed ".count($this->stamped);
    }

    /** @var list<array<string, mixed>> */
    public array $watermarked = [];

    public function watermark(array $input): string
    {
        if ($this->fail) {
            throw new PdfEngineException('The PDF engine answered 500: boom');
        }

        $this->watermarked[] = $input;

        return $input['pdf']."\n% watermark {$input['status']}";
    }

    /**
     * A tiny but structurally valid one-page PDF.
     */
    public static function pdf(string $text = 'test'): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n% {$text}\ntrailer<</Root 1 0 R>>\n%%EOF";
    }
}
